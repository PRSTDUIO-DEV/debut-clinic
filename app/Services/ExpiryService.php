<?php

namespace App\Services;

use App\Models\StockLevel;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ExpiryService
{
    public const GREEN = 'green';

    public const YELLOW = 'yellow';

    public const ORANGE = 'orange';

    public const RED = 'red';

    public const EXPIRED = 'expired';

    /**
     * Classify a stock level lot into one of 5 expiry buckets.
     */
    public function classify(?CarbonInterface $expiry, ?CarbonInterface $today = null): string
    {
        if (! $expiry) {
            return self::GREEN;
        }
        $today = $today ? $today->copy()->startOfDay() : Carbon::today();
        $expiry = $expiry->copy()->startOfDay();

        if ($expiry->lte($today)) {
            return self::EXPIRED;
        }
        $diffDays = $today->diffInDays($expiry, false);
        if ($diffDays < 30) {
            return self::RED;
        }
        if ($diffDays < 90) {
            return self::ORANGE;
        }
        if ($diffDays < 180) {
            return self::YELLOW;
        }

        return self::GREEN;
    }

    public function classifyLevel(StockLevel $level): string
    {
        return $this->classify($level->expiry_date);
    }
}
