<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class HnGenerator
{
    /**
     * Generate sequential HN per branch per day.
     * Format: <BRANCH_CODE>-YYMMDD-#### (e.g. DC01-260426-0001)
     *
     * Use this within a transaction to keep the count and insert atomic.
     */
    public function nextFor(Branch $branch, ?\DateTimeInterface $date = null): string
    {
        $date = $date ?? now();
        $tag = $date->format('ymd');
        $prefix = strtoupper($branch->code).'-'.$tag.'-';

        return DB::transaction(function () use ($branch, $prefix) {
            // Bypass BranchScope for accurate per-branch lookup
            $existing = Patient::withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->where('hn', 'like', $prefix.'%')
                ->lockForUpdate()
                ->pluck('hn');

            $maxSeq = 0;
            foreach ($existing as $hn) {
                $tail = substr((string) $hn, strlen($prefix));
                if (ctype_digit($tail)) {
                    $maxSeq = max($maxSeq, (int) $tail);
                }
            }

            $next = $maxSeq + 1;

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
