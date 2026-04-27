<?php

namespace App\Services;

use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class VisitNumberGenerator
{
    /**
     * Format: VN-YYYYMMDD-#### (sequential per day, global to system).
     */
    public function next(?\DateTimeInterface $date = null): string
    {
        $date = $date ?? now();
        $tag = $date->format('Ymd');
        $prefix = 'VN-'.$tag.'-';

        return DB::transaction(function () use ($prefix) {
            $existing = Visit::withoutGlobalScopes()
                ->where('visit_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->pluck('visit_number');

            $maxSeq = 0;
            foreach ($existing as $vn) {
                $tail = substr((string) $vn, strlen($prefix));
                if (ctype_digit($tail)) {
                    $maxSeq = max($maxSeq, (int) $tail);
                }
            }

            return $prefix.str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);
        });
    }
}
