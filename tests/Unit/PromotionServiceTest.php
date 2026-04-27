<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Promotion;
use App\Services\Marketing\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_percent_promotion_applies_to_full_cart(): void
    {
        $branch = Branch::factory()->create();
        $promo = Promotion::create([
            'branch_id' => $branch->id, 'name' => '20% off',
            'type' => 'percent', 'rules' => ['value' => 20, 'min_amount' => 1000],
            'valid_from' => now()->toDateString(), 'valid_to' => now()->addDay()->toDateString(),
            'is_active' => true,
        ]);

        $r = app(PromotionService::class)->applyToCart($promo, [
            'subtotal' => 5000,
            'items' => [['item_id' => 1, 'item_type' => 'procedure', 'unit_price' => 5000, 'quantity' => 1, 'total' => 5000]],
        ]);
        $this->assertTrue($r['applied']);
        $this->assertSame(1000.0, $r['discount']);
    }

    public function test_promotion_skipped_when_below_min_amount(): void
    {
        $branch = Branch::factory()->create();
        $promo = Promotion::create([
            'branch_id' => $branch->id, 'name' => 'Min',
            'type' => 'percent', 'rules' => ['value' => 20, 'min_amount' => 5000],
            'valid_from' => now()->toDateString(), 'valid_to' => now()->addDay()->toDateString(),
            'is_active' => true,
        ]);

        $r = app(PromotionService::class)->applyToCart($promo, ['subtotal' => 1000, 'items' => []]);
        $this->assertFalse($r['applied']);
        $this->assertEquals(0, $r['discount']);
    }

    public function test_buy_x_get_y_returns_cheapest_free(): void
    {
        $branch = Branch::factory()->create();
        $promo = Promotion::create([
            'branch_id' => $branch->id, 'name' => '1+1',
            'type' => 'buy_x_get_y',
            'rules' => ['buy_qty' => 1, 'get_qty' => 1],
            'valid_from' => now()->toDateString(), 'valid_to' => now()->addDay()->toDateString(),
            'is_active' => true,
        ]);

        $r = app(PromotionService::class)->applyToCart($promo, [
            'subtotal' => 3000,
            'items' => [
                ['item_id' => 1, 'item_type' => 'procedure', 'unit_price' => 1000, 'quantity' => 1, 'total' => 1000],
                ['item_id' => 2, 'item_type' => 'procedure', 'unit_price' => 2000, 'quantity' => 1, 'total' => 2000],
            ],
        ]);
        $this->assertTrue($r['applied']);
        $this->assertSame(1000.0, $r['discount']); // cheapest of the pair = 1000
    }

    public function test_best_for_cart_picks_highest_discount(): void
    {
        $branch = Branch::factory()->create();
        Promotion::create([
            'branch_id' => $branch->id, 'name' => '10%',
            'type' => 'percent', 'rules' => ['value' => 10],
            'valid_from' => now()->toDateString(), 'valid_to' => now()->addDay()->toDateString(),
            'is_active' => true, 'priority' => 1,
        ]);
        Promotion::create([
            'branch_id' => $branch->id, 'name' => '500 off',
            'type' => 'fixed', 'rules' => ['value' => 500],
            'valid_from' => now()->toDateString(), 'valid_to' => now()->addDay()->toDateString(),
            'is_active' => true, 'priority' => 2,
        ]);

        $best = app(PromotionService::class)->bestForCart($branch->id, [
            'subtotal' => 3000,
            'items' => [['item_id' => 1, 'item_type' => 'procedure', 'unit_price' => 3000, 'quantity' => 1, 'total' => 3000]],
        ]);
        $this->assertSame(500.0, $best['discount']); // 10% of 3000 = 300; 500 fixed wins
    }
}
