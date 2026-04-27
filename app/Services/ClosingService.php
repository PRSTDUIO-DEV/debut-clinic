<?php

namespace App\Services;

use App\Models\DailyClosing;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClosingService
{
    /**
     * Compute snapshot for the given date and persist as draft.
     * If a draft already exists, update it. If already closed, return as-is.
     */
    public function prepare(int $branchId, ?string $date = null): DailyClosing
    {
        $date = $date ?? now()->toDateString();
        $existing = DailyClosing::query()
            ->where('branch_id', $branchId)
            ->whereDate('closing_date', $date)
            ->first();
        if ($existing && $existing->status === 'closed') {
            return $existing;
        }

        $snapshot = $this->computeSnapshot($branchId, $date);

        if ($existing) {
            $existing->fill(array_merge($snapshot, ['status' => 'draft']))->save();

            return $existing->fresh();
        }

        return DailyClosing::create(array_merge($snapshot, [
            'branch_id' => $branchId,
            'closing_date' => $date,
            'status' => 'draft',
        ]));
    }

    public function commit(DailyClosing $closing, float $countedCash, ?User $user = null, ?string $notes = null): DailyClosing
    {
        return DB::transaction(function () use ($closing, $countedCash, $user, $notes) {
            $closing = DailyClosing::query()->lockForUpdate()->findOrFail($closing->id);
            if ($closing->status === 'closed') {
                throw ValidationException::withMessages(['closing' => 'วันนี้ถูกปิดบัญชีไปแล้ว']);
            }

            $closing->counted_cash = $countedCash;
            $closing->variance = round($countedCash - (float) $closing->expected_cash, 2);
            $closing->status = 'closed';
            $closing->closed_by = $user?->id;
            $closing->closed_at = now();
            if ($notes) {
                $closing->notes = $notes;
            }
            $closing->save();

            return $closing->fresh();
        });
    }

    public function reopen(DailyClosing $closing, string $reason, ?User $user = null): DailyClosing
    {
        if ($closing->status !== 'closed') {
            throw ValidationException::withMessages(['closing' => 'ต้องเป็นสถานะ closed เท่านั้นที่ reopen ได้']);
        }
        $closing->status = 'reopened';
        $closing->notes = trim(($closing->notes ? $closing->notes."\n" : '').'[REOPEN by '.($user?->name ?? '?').'] '.$reason);
        $closing->save();

        return $closing->fresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function computeSnapshot(int $branchId, string $date): array
    {
        $invoiceIds = DB::table('invoices')
            ->where('branch_id', $branchId)
            ->whereDate('invoice_date', $date)
            ->where('status', 'paid')
            ->pluck('id')
            ->all();

        $totals = (object) [
            'total_revenue' => 0.0,
            'total_cogs' => 0.0,
            'total_commission' => 0.0,
            'total_mdr' => 0.0,
        ];

        if (! empty($invoiceIds)) {
            $r = DB::table('invoices')
                ->whereIn('id', $invoiceIds)
                ->selectRaw('SUM(total_amount) as r, SUM(total_cogs) as c, SUM(total_commission) as cm')
                ->first();
            $totals->total_revenue = (float) ($r->r ?? 0);
            $totals->total_cogs = (float) ($r->c ?? 0);
            $totals->total_commission = (float) ($r->cm ?? 0);

            $totals->total_mdr = (float) DB::table('payments')
                ->whereIn('invoice_id', $invoiceIds)
                ->sum('mdr_amount');
        }

        $totalExpenses = (float) DB::table('expenses')
            ->where('branch_id', $branchId)
            ->whereDate('expense_date', $date)
            ->whereNull('deleted_at')
            ->sum('amount');

        $cashPayments = ! empty($invoiceIds) ? (float) DB::table('payments')
            ->whereIn('invoice_id', $invoiceIds)
            ->where('method', 'cash')
            ->sum('amount') : 0.0;
        $cashExpenses = (float) DB::table('expenses')
            ->where('branch_id', $branchId)
            ->whereDate('expense_date', $date)
            ->where('payment_method', 'cash')
            ->whereNull('deleted_at')
            ->sum('amount');
        $expectedCash = round($cashPayments - $cashExpenses, 2);

        $breakdown = ['cash' => 0.0, 'credit_card' => 0.0, 'transfer' => 0.0, 'member_credit' => 0.0, 'coupon' => 0.0];
        if (! empty($invoiceIds)) {
            $rows = DB::table('payments')
                ->whereIn('invoice_id', $invoiceIds)
                ->select('method')
                ->selectRaw('SUM(amount) as total')
                ->groupBy('method')
                ->get();
            foreach ($rows as $r) {
                $breakdown[$r->method] = (float) $r->total;
            }
        }

        $gross = round($totals->total_revenue - $totals->total_cogs - $totals->total_commission - $totals->total_mdr, 2);
        $net = round($gross - $totalExpenses, 2);

        return [
            'expected_cash' => $expectedCash,
            'counted_cash' => 0,
            'variance' => 0,
            'total_revenue' => $totals->total_revenue,
            'total_cogs' => $totals->total_cogs,
            'total_commission' => $totals->total_commission,
            'total_mdr' => $totals->total_mdr,
            'total_expenses' => $totalExpenses,
            'gross_profit' => $gross,
            'net_profit' => $net,
            'payment_breakdown' => $breakdown,
        ];
    }

    public function autoPrepareYesterday(?int $branchId = null): int
    {
        $date = Carbon::yesterday()->toDateString();
        $count = 0;
        $branchIds = $branchId ? [$branchId] : DB::table('branches')->pluck('id')->all();
        foreach ($branchIds as $bid) {
            $this->prepare($bid, $date);
            $count++;
        }

        return $count;
    }
}
