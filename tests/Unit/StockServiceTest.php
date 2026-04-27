<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bootEnv(): array
    {
        $branch = Branch::factory()->create();
        $product = Product::create([
            'branch_id' => $branch->id, 'sku' => 'SKU01', 'name' => 'Saline 0.9% 1L',
            'unit' => 'ขวด', 'selling_price' => 100, 'cost_price' => 50,
            'min_stock' => 5, 'reorder_point' => 10, 'is_active' => true,
            'block_dispensing_when_expired' => true,
        ]);
        $main = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Main', 'type' => 'main']);
        $floor = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Floor', 'type' => 'floor']);

        return [$branch, $product, $main, $floor];
    }

    public function test_deduct_picks_oldest_expiry_first_fifo(): void
    {
        [$branch, $product, , $floor] = $this->bootEnv();
        StockLevel::create([
            'product_id' => $product->id, 'warehouse_id' => $floor->id,
            'quantity' => 10, 'lot_no' => 'L1', 'expiry_date' => now()->addDays(60), 'cost_price' => 40,
        ]);
        StockLevel::create([
            'product_id' => $product->id, 'warehouse_id' => $floor->id,
            'quantity' => 10, 'lot_no' => 'L2', 'expiry_date' => now()->addDays(200), 'cost_price' => 60,
        ]);

        $svc = $this->app->make(StockService::class);
        $consumed = $svc->deduct(
            productId: $product->id, warehouseId: $floor->id, qty: 12,
            branchId: $branch->id, refType: 'test', refId: 1,
        );

        $this->assertCount(2, $consumed);
        $this->assertSame('L1', $consumed[0]['lot_no']);
        $this->assertSame(10, $consumed[0]['qty']);
        $this->assertSame('L2', $consumed[1]['lot_no']);
        $this->assertSame(2, $consumed[1]['qty']);
    }

    public function test_deduct_throws_when_insufficient_stock(): void
    {
        [$branch, $product, , $floor] = $this->bootEnv();
        StockLevel::create([
            'product_id' => $product->id, 'warehouse_id' => $floor->id,
            'quantity' => 3, 'lot_no' => 'L1', 'expiry_date' => now()->addDays(60), 'cost_price' => 40,
        ]);

        $this->expectException(ValidationException::class);
        $this->app->make(StockService::class)->deduct(
            productId: $product->id, warehouseId: $floor->id, qty: 10,
            branchId: $branch->id, refType: 'test', refId: 1,
        );
    }

    public function test_deduct_skips_expired_lot_when_blocked(): void
    {
        [$branch, $product, , $floor] = $this->bootEnv();
        StockLevel::create([
            'product_id' => $product->id, 'warehouse_id' => $floor->id,
            'quantity' => 5, 'lot_no' => 'EXPIRED', 'expiry_date' => now()->subDays(1), 'cost_price' => 40,
        ]);
        StockLevel::create([
            'product_id' => $product->id, 'warehouse_id' => $floor->id,
            'quantity' => 5, 'lot_no' => 'GOOD', 'expiry_date' => now()->addDays(60), 'cost_price' => 50,
        ]);

        $consumed = $this->app->make(StockService::class)->deduct(
            productId: $product->id, warehouseId: $floor->id, qty: 3,
            branchId: $branch->id, refType: 'test', refId: 1,
        );

        $this->assertSame('GOOD', $consumed[0]['lot_no']);
    }

    public function test_adjust_writes_movement_and_updates_quantity(): void
    {
        [$branch, $product, , $floor] = $this->bootEnv();
        StockLevel::create([
            'product_id' => $product->id, 'warehouse_id' => $floor->id,
            'quantity' => 20, 'lot_no' => 'L1', 'cost_price' => 40,
        ]);

        $level = $this->app->make(StockService::class)->adjust(
            productId: $product->id, warehouseId: $floor->id,
            delta: -3, reason: 'damage', branchId: $branch->id, lotNo: 'L1',
        );

        $this->assertSame(17, $level->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'warehouse_id' => $floor->id,
            'type' => 'adjust', 'quantity' => -3, 'before_qty' => 20, 'after_qty' => 17,
        ]);
    }
}
