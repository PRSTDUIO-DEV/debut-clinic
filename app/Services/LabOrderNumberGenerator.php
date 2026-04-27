<?php

namespace App\Services;

use App\Models\LabOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LabOrderNumberGenerator
{
    /**
     * Format: LAB-yyyymmdd-#### (per-day sequence per branch).
     */
    public function next(int $branchId, ?Carbon $date = null): string
    {
        $date = $date ?? Carbon::today();
        $prefix = 'LAB-'.$date->format('Ymd').'-';

        return DB::transaction(function () use ($prefix, $branchId) {
            $last = LabOrder::query()
                ->where('branch_id', $branchId)
                ->where('order_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('order_no')
                ->value('order_no');

            $seq = 1;
            if ($last) {
                $tail = (int) substr($last, strlen($prefix));
                $seq = $tail + 1;
            }

            return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
