<?php

namespace App\Services\Hr;

use App\Models\CommissionTransaction;
use App\Models\Disbursement;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\User;
use App\Services\Accounting\DisbursementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(
        private TimeClockService $timeClock,
        private CompensationService $compensation,
    ) {}

    /**
     * Build a draft payroll for a branch + month, with one item per active employee.
     * Idempotent — re-creates the draft from scratch if it exists in draft state.
     */
    public function generatePreview(int $branchId, int $year, int $month): Payroll
    {
        return DB::transaction(function () use ($branchId, $year, $month) {
            $payroll = Payroll::firstOrCreate(
                ['branch_id' => $branchId, 'period_year' => $year, 'period_month' => $month],
                ['status' => 'draft', 'total_amount' => 0],
            );

            if ($payroll->status !== 'draft') {
                throw ValidationException::withMessages(['payroll' => 'Payroll นี้ถูก finalize แล้ว ไม่สามารถ regenerate ได้']);
            }

            // Wipe & rebuild items for draft
            $payroll->items()->delete();

            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $users = User::query()
                ->where('is_active', true)
                ->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId))
                ->get();

            $totalNet = 0.0;
            foreach ($users as $user) {
                $rule = $this->compensation->resolveRate($user, $end->toDateString());
                $monthly = $this->timeClock->monthlySummary($user->id, $year, $month);

                $base = 0.0;
                $compType = 'none';
                if ($rule) {
                    $base = $this->compensation->computeBasePay($rule, (float) $monthly['total_hours'], (int) $monthly['days_worked']);
                    $compType = $rule->type;
                }

                $commissionTotal = (float) CommissionTransaction::where('user_id', $user->id)
                    ->where('branch_id', $branchId)
                    ->whereDate('commission_date', '>=', $start->toDateString())
                    ->whereDate('commission_date', '<=', $end->toDateString())
                    ->sum('amount');

                $otHours = (float) $monthly['overtime_hours'];
                $hourlyRate = $rule && $rule->type === 'hourly' ? (float) $rule->base_amount : ($base > 0 && $monthly['total_hours'] > 0 ? $base / max(1, $monthly['total_hours']) : 0);
                $otPay = round($otHours * $hourlyRate * 1.5, 2);

                $netPay = round($base + $commissionTotal + $otPay, 2);
                $totalNet += $netPay;

                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'user_id' => $user->id,
                    'base_pay' => $base,
                    'commission_total' => $commissionTotal,
                    'overtime_pay' => $otPay,
                    'bonus' => 0,
                    'deduction' => 0,
                    'net_pay' => $netPay,
                    'hours_worked' => $monthly['total_hours'],
                    'days_worked' => $monthly['days_worked'],
                    'late_count' => $monthly['late_count'],
                    'compensation_type' => $compType,
                ]);
            }

            $payroll->total_amount = round($totalNet, 2);
            $payroll->save();

            return $payroll->fresh('items');
        });
    }

    public function adjustItem(PayrollItem $item, ?float $bonus = null, ?float $deduction = null, ?string $notes = null): PayrollItem
    {
        $payroll = $item->payroll;
        if ($payroll->status !== 'draft') {
            throw ValidationException::withMessages(['payroll' => 'ปรับค่าได้เฉพาะ payroll สถานะ draft']);
        }
        if ($bonus !== null) {
            $item->bonus = $bonus;
        }
        if ($deduction !== null) {
            $item->deduction = $deduction;
        }
        if ($notes !== null) {
            $item->notes = $notes;
        }

        $item->net_pay = round(
            (float) $item->base_pay
            + (float) $item->commission_total
            + (float) $item->overtime_pay
            + (float) $item->bonus
            - (float) $item->deduction,
            2,
        );
        $item->save();

        // Recompute payroll total
        $payroll->total_amount = (float) $payroll->items()->sum('net_pay');
        $payroll->save();

        return $item->fresh();
    }

    public function finalize(Payroll $payroll, User $finalizer): Payroll
    {
        if ($payroll->status !== 'draft') {
            throw ValidationException::withMessages(['payroll' => 'Payroll ถูก finalize แล้ว']);
        }
        $payroll->status = 'finalized';
        $payroll->finalized_at = now();
        $payroll->finalized_by = $finalizer->id;
        $payroll->save();

        // Mark commission_transactions as paid for this period
        $start = Carbon::create($payroll->period_year, $payroll->period_month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        CommissionTransaction::where('branch_id', $payroll->branch_id)
            ->whereDate('commission_date', '>=', $start->toDateString())
            ->whereDate('commission_date', '<=', $end->toDateString())
            ->where('is_paid', false)
            ->update(['is_paid' => true, 'paid_at' => now()]);

        return $payroll->fresh();
    }

    /**
     * Mark as paid + create matching disbursement (if available).
     */
    public function markPaid(Payroll $payroll, User $user, ?string $paymentMethod = 'transfer', ?string $reference = null): Payroll
    {
        if ($payroll->status !== 'finalized') {
            throw ValidationException::withMessages(['payroll' => 'ต้อง finalize ก่อน mark-paid']);
        }

        return DB::transaction(function () use ($payroll, $user, $paymentMethod, $reference) {
            $payroll->status = 'paid';
            $payroll->paid_at = now();
            $payroll->payment_method = $paymentMethod;
            $payroll->payment_reference = $reference;
            $payroll->save();

            // Best-effort: create a disbursement that posts accounting entry
            try {
                $svc = app(DisbursementService::class);
                $disbursement = Disbursement::create([
                    'branch_id' => $payroll->branch_id,
                    'disbursement_no' => $svc->nextNumber($payroll->branch_id),
                    'disbursement_date' => now()->toDateString(),
                    'type' => 'salary',
                    'amount' => (float) $payroll->total_amount,
                    'payment_method' => $paymentMethod ?: 'transfer',
                    'reference' => $reference,
                    'description' => sprintf('Payroll %d/%02d', $payroll->period_year, $payroll->period_month),
                    'requested_by' => $user->id,
                    'status' => 'draft',
                ]);
                $svc->approve($disbursement, $user);
                $svc->pay($disbursement, $reference, $user);
            } catch (\Throwable $e) {
                Log::warning('Payroll disbursement failed: '.$e->getMessage());
            }

            return $payroll->fresh();
        });
    }
}
