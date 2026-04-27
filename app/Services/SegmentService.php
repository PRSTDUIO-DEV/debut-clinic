<?php

namespace App\Services;

use App\Models\BroadcastSegment;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class SegmentService
{
    /**
     * Build the patient query from segment rules.
     *
     * Supported rule keys:
     * - customer_group_ids: array<int>
     * - last_visit_days_max: int (e.g. <= N days ago)
     * - last_visit_days_min: int (e.g. >= N days ago)
     * - has_active_course: bool
     * - has_member_account: bool
     * - total_spent_min: float
     * - total_spent_max: float
     * - gender: 'male'|'female'|'other'
     * - age_min: int
     * - age_max: int
     */
    public function query(BroadcastSegment $segment): Builder
    {
        $rules = (array) $segment->rules;

        $q = Patient::query()->where('branch_id', $segment->branch_id);

        if (! empty($rules['customer_group_ids'])) {
            $q->whereIn('customer_group_id', (array) $rules['customer_group_ids']);
        }

        if (isset($rules['gender'])) {
            $q->where('gender', $rules['gender']);
        }

        if (isset($rules['total_spent_min'])) {
            $q->where('total_spent', '>=', (float) $rules['total_spent_min']);
        }
        if (isset($rules['total_spent_max'])) {
            $q->where('total_spent', '<=', (float) $rules['total_spent_max']);
        }

        if (isset($rules['last_visit_days_max'])) {
            $threshold = Carbon::now()->subDays((int) $rules['last_visit_days_max']);
            $q->where('last_visit_at', '>=', $threshold);
        }
        if (isset($rules['last_visit_days_min'])) {
            $threshold = Carbon::now()->subDays((int) $rules['last_visit_days_min']);
            $q->where(function ($qq) use ($threshold) {
                $qq->where('last_visit_at', '<=', $threshold)->orWhereNull('last_visit_at');
            });
        }

        if (isset($rules['age_min'])) {
            $cutoff = Carbon::now()->subYears((int) $rules['age_min'])->toDateString();
            $q->whereNotNull('date_of_birth')->whereDate('date_of_birth', '<=', $cutoff);
        }
        if (isset($rules['age_max'])) {
            $cutoff = Carbon::now()->subYears((int) $rules['age_max'])->toDateString();
            $q->whereNotNull('date_of_birth')->whereDate('date_of_birth', '>=', $cutoff);
        }

        if (! empty($rules['has_member_account'])) {
            $q->whereHas('memberAccount', fn ($m) => $m->where('status', 'active'));
        }

        if (! empty($rules['has_active_course'])) {
            $q->whereHas('courses', fn ($c) => $c->where('status', 'active')->where('remaining_sessions', '>', 0));
        }

        return $q;
    }

    public function resolve(BroadcastSegment $segment): Collection
    {
        return $this->query($segment)->get();
    }

    public function count(BroadcastSegment $segment): int
    {
        return $this->query($segment)->count();
    }

    /**
     * Refresh denormalized stats on the segment row.
     */
    public function touchStats(BroadcastSegment $segment): BroadcastSegment
    {
        $segment->last_resolved_count = $this->count($segment);
        $segment->last_resolved_at = now();
        $segment->save();

        return $segment;
    }
}
