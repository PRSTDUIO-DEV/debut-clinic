<?php

namespace App\Services;

use App\Models\FollowUp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FollowUpPriorityService
{
    /**
     * Compute priority for a follow-up given today's date.
     *
     * Logic:
     *  - critical: overdue > 7 days
     *  - high:     overdue 3-7 days OR notes contain "[critical]" / "[vip]" tag
     *  - normal:   overdue 0-3 days OR future-dated default
     *  - low:      future > 30 days OR no_show signal
     */
    public function classify(FollowUp $f, ?Carbon $now = null): string
    {
        $now = ($now ?? now())->copy()->startOfDay();
        $due = $f->follow_up_date ? $f->follow_up_date->copy()->startOfDay() : null;
        if (! $due) {
            return 'normal';
        }

        $overdue = $now->diffInDays($due, false) * -1; // positive when overdue
        $notes = strtolower((string) ($f->notes ?? ''));

        if ($overdue > 7) {
            return 'critical';
        }
        if (str_contains($notes, '[critical]') || str_contains($notes, '[vip]')) {
            return 'critical';
        }
        if ($overdue >= 3) {
            return 'high';
        }
        if ($overdue < -30) {
            return 'low';
        }

        return 'normal';
    }

    /**
     * Recalculate priorities for all open follow-ups.
     *
     * @return int Number of rows updated
     */
    public function recalculateAll(?int $branchId = null): int
    {
        $query = FollowUp::query()
            ->whereNotIn('status', ['completed', 'cancelled']);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $updated = 0;
        $now = now();

        DB::transaction(function () use ($query, $now, &$updated) {
            foreach ($query->cursor() as $f) {
                $next = $this->classify($f, $now);
                if ($f->priority !== $next) {
                    $f->priority = $next;
                    $f->saveQuietly();
                    $updated++;
                }
            }
        });

        return $updated;
    }
}
