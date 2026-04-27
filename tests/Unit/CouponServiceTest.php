<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\Marketing\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_creates_n_unique_coupons_with_prefix(): void
    {
        $branch = Branch::factory()->create();
        $svc = app(CouponService::class);
        $coupons = $svc->generate($branch->id, [
            'name' => 'Demo',
            'type' => 'percent',
            'value' => 10,
            'valid_from' => now()->toDateString(),
            'valid_to' => now()->addMonth()->toDateString(),
        ], 5, 'BTX');

        $this->assertCount(5, $coupons);
        foreach ($coupons as $c) {
            $this->assertStringStartsWith('BTX-', $c->code);
        }
        $this->assertCount(5, array_unique(array_column(array_map(fn ($c) => $c->toArray(), $coupons), 'code')));
    }

    public function test_validate_rejects_expired_coupon(): void
    {
        $branch = Branch::factory()->create();
        $coupon = Coupon::create([
            'branch_id' => $branch->id, 'code' => 'EXPIRED', 'name' => 'X',
            'type' => 'percent', 'value' => 10, 'is_active' => true,
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => now()->subDay()->toDateString(),
            'max_per_customer' => 1,
        ]);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->validate('EXPIRED', null, 1000, $branch->id);
    }

    public function test_validate_rejects_below_min_amount(): void
    {
        $branch = Branch::factory()->create();
        Coupon::create([
            'branch_id' => $branch->id, 'code' => 'MIN5K', 'name' => 'X',
            'type' => 'percent', 'value' => 10, 'is_active' => true, 'min_amount' => 5000,
            'valid_from' => now()->toDateString(), 'valid_to' => now()->addDay()->toDateString(),
            'max_per_customer' => 1,
        ]);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->validate('MIN5K', null, 1000, $branch->id);
    }

    public function test_validate_calculates_percent_discount_with_max_cap(): void
    {
        $branch = Branch::factory()->create();
        Coupon::create([
            'branch_id' => $branch->id, 'code' => 'TEN', 'name' => 'X',
            'type' => 'percent', 'value' => 20, 'max_discount' => 500, 'is_active' => true,
            'valid_from' => now()->toDateString(), 'valid_to' => now()->addDay()->toDateString(),
            'max_per_customer' => 1,
        ]);

        $r = app(CouponService::class)->validate('TEN', null, 5000, $branch->id);
        $this->assertSame(500.0, $r['discount']); // 20% of 5000 = 1000, capped at 500
    }

    public function test_redeem_increments_used_count_and_logs_redemption(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $coupon = Coupon::create([
            'branch_id' => $branch->id, 'code' => 'USE1', 'name' => 'X',
            'type' => 'fixed', 'value' => 100, 'is_active' => true, 'max_uses' => 5,
            'valid_from' => now()->toDateString(), 'valid_to' => now()->addDay()->toDateString(),
            'max_per_customer' => 1,
        ]);
        $visit = Visit::factory()->create(['branch_id' => $branch->id, 'patient_id' => $patient->id]);
        $invoice = Invoice::create([
            'branch_id' => $branch->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id,
            'invoice_number' => 'INV-T-001', 'invoice_date' => now()->toDateString(),
            'subtotal' => 1000, 'total_amount' => 900, 'status' => 'paid',
        ]);

        app(CouponService::class)->redeem($coupon, $invoice, 100);
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertSame(1, CouponRedemption::where('coupon_id', $coupon->id)->count());
    }

    public function test_per_customer_limit_blocks_repeat_use(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $coupon = Coupon::create([
            'branch_id' => $branch->id, 'code' => 'ONE', 'name' => 'X',
            'type' => 'fixed', 'value' => 100, 'is_active' => true,
            'valid_from' => now()->toDateString(), 'valid_to' => now()->addDay()->toDateString(),
            'max_per_customer' => 1,
        ]);
        CouponRedemption::create([
            'coupon_id' => $coupon->id, 'patient_id' => $patient->id,
            'amount_discounted' => 100, 'redeemed_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->validate('ONE', $patient, 1000, $branch->id);
    }
}
