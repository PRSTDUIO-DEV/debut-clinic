<?php

namespace Tests\Unit;

use App\Services\ExpiryService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class ExpiryServiceTest extends TestCase
{
    public function test_classify_buckets(): void
    {
        $svc = new ExpiryService;
        $today = Carbon::create(2026, 4, 26);

        $this->assertSame(ExpiryService::EXPIRED, $svc->classify($today->copy()->subDay(), $today));
        $this->assertSame(ExpiryService::EXPIRED, $svc->classify($today->copy(), $today));
        $this->assertSame(ExpiryService::RED, $svc->classify($today->copy()->addDays(15), $today));
        $this->assertSame(ExpiryService::ORANGE, $svc->classify($today->copy()->addDays(60), $today));
        $this->assertSame(ExpiryService::YELLOW, $svc->classify($today->copy()->addDays(150), $today));
        $this->assertSame(ExpiryService::GREEN, $svc->classify($today->copy()->addDays(365), $today));
    }

    public function test_classify_returns_green_when_no_expiry(): void
    {
        $svc = new ExpiryService;
        $this->assertSame(ExpiryService::GREEN, $svc->classify(null));
    }
}
