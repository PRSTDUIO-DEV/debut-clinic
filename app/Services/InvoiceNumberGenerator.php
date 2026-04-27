<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceNumberGenerator
{
    /**
     * Format: INV-YYYYMM-#### (sequential per month).
     */
    public function next(?\DateTimeInterface $date = null): string
    {
        $date = $date ?? now();
        $tag = $date->format('Ym');
        $prefix = 'INV-'.$tag.'-';

        return DB::transaction(function () use ($prefix) {
            $existing = Invoice::withoutGlobalScopes()
                ->where('invoice_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->pluck('invoice_number');

            $maxSeq = 0;
            foreach ($existing as $no) {
                $tail = substr((string) $no, strlen($prefix));
                if (ctype_digit($tail)) {
                    $maxSeq = max($maxSeq, (int) $tail);
                }
            }

            return $prefix.str_pad((string) ($maxSeq + 1), 4, '0', STR_PAD_LEFT);
        });
    }
}
