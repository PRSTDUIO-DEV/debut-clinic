<?php

namespace App\Services;

use App\Models\CommissionRate;
use App\Models\CommissionTransaction;
use App\Models\InvoiceItem;
use App\Models\Procedure;
use Illuminate\Database\Eloquent\Builder;

class CommissionService
{
    /**
     * Resolve a commission rate for the given context.
     *
     * Lookup priority (high → low):
     *   1. user_id + applicable_type=procedure + applicable_id=procedure.id
     *   2. user_id + applicable_type=all
     *   3. applicable_type=procedure + applicable_id=procedure.id (no user)
     *   4. applicable_type=procedure_category + applicable_id=category_id (no user)
     *   5. applicable_type=all (no user)
     *   6. fallback: read procedures.doctor_fee_rate / staff_commission_rate
     *
     * @return array{rate:float|null, fixed_amount:float|null, source:string}
     */
    public function resolveRate(
        int $branchId,
        string $type,
        ?int $userId,
        Procedure $procedure,
    ): array {
        $base = CommissionRate::query()
            ->where('branch_id', $branchId)
            ->where('type', $type)
            ->where('is_active', true);

        $candidates = [
            // 1. user + procedure
            ['user_id' => $userId, 'applicable_type' => 'procedure', 'applicable_id' => $procedure->id, 'priority' => 100],
            // 2. user + all
            ['user_id' => $userId, 'applicable_type' => 'all', 'applicable_id' => null, 'priority' => 90],
            // 3. procedure (no user)
            ['user_id' => null, 'applicable_type' => 'procedure', 'applicable_id' => $procedure->id, 'priority' => 80],
            // 4. procedure_category (placeholder via category string match — categories not normalized yet)
            // skipping until category table exists
            // 5. all (no user)
            ['user_id' => null, 'applicable_type' => 'all', 'applicable_id' => null, 'priority' => 60],
        ];

        foreach ($candidates as $c) {
            if ($c['user_id'] === null && isset($c['user_id_skip'])) {
                continue;
            }
            $row = (clone $base)
                ->when($c['user_id'] !== null, fn (Builder $q) => $q->where('user_id', $c['user_id']))
                ->when($c['user_id'] === null, fn (Builder $q) => $q->whereNull('user_id'))
                ->where('applicable_type', $c['applicable_type'])
                ->when($c['applicable_id'] !== null, fn (Builder $q) => $q->where('applicable_id', $c['applicable_id']))
                ->when($c['applicable_id'] === null, fn (Builder $q) => $q->whereNull('applicable_id'))
                ->first();

            if ($row) {
                return [
                    'rate' => $row->rate !== null ? (float) $row->rate : null,
                    'fixed_amount' => $row->fixed_amount !== null ? (float) $row->fixed_amount : null,
                    'source' => 'commission_rates#'.$row->id,
                ];
            }
        }

        // Fallback to procedure-level default
        if ($type === 'doctor_fee') {
            return ['rate' => (float) $procedure->doctor_fee_rate, 'fixed_amount' => null, 'source' => 'procedure.doctor_fee_rate'];
        }
        if ($type === 'staff_commission') {
            return ['rate' => (float) $procedure->staff_commission_rate, 'fixed_amount' => null, 'source' => 'procedure.staff_commission_rate'];
        }

        return ['rate' => 0.0, 'fixed_amount' => null, 'source' => 'default_zero'];
    }

    /**
     * Calculate amount given resolved rate against base.
     */
    public function calculate(array $resolved, float $baseAmount): float
    {
        if ($resolved['fixed_amount'] !== null) {
            return round((float) $resolved['fixed_amount'], 2);
        }

        $rate = (float) ($resolved['rate'] ?? 0);

        return round($baseAmount * $rate / 100, 2);
    }

    /**
     * Build (but do not persist) commission transactions for an invoice item.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildForItem(InvoiceItem $item, int $branchId, ?\DateTimeInterface $date = null): array
    {
        $rows = [];
        if ($item->item_type !== 'procedure') {
            return $rows;
        }

        $procedure = Procedure::query()->find($item->item_id);
        if (! $procedure) {
            return $rows;
        }

        $date = ($date ?? now())->format('Y-m-d');

        if ($item->doctor_id) {
            $resolved = $this->resolveRate($branchId, 'doctor_fee', (int) $item->doctor_id, $procedure);
            $amount = $this->calculate($resolved, (float) $item->total);
            if ($amount > 0) {
                $rows[] = [
                    'branch_id' => $branchId,
                    'invoice_item_id' => $item->id,
                    'user_id' => $item->doctor_id,
                    'type' => 'doctor_fee',
                    'base_amount' => (float) $item->total,
                    'rate' => $resolved['rate'],
                    'amount' => $amount,
                    'commission_date' => $date,
                ];
            }
        }

        if ($item->staff_id) {
            $resolved = $this->resolveRate($branchId, 'staff_commission', (int) $item->staff_id, $procedure);
            $amount = $this->calculate($resolved, (float) $item->total);
            if ($amount > 0) {
                $rows[] = [
                    'branch_id' => $branchId,
                    'invoice_item_id' => $item->id,
                    'user_id' => $item->staff_id,
                    'type' => 'staff_commission',
                    'base_amount' => (float) $item->total,
                    'rate' => $resolved['rate'],
                    'amount' => $amount,
                    'commission_date' => $date,
                ];
            }
        }

        return $rows;
    }

    /**
     * Persist commission transactions and return inserted rows.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function persist(array $rows): array
    {
        $created = [];
        foreach ($rows as $r) {
            $created[] = CommissionTransaction::create($r);
        }

        return $created;
    }
}
