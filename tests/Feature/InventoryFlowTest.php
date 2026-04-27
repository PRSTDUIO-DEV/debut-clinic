<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function bootAdmin(): array
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->branches()->attach($branch->id, ['is_primary' => true]);
        $admin->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($admin);

        return [$branch, $admin];
    }

    public function test_receiving_creates_stock_level_and_movement(): void
    {
        [$branch] = $this->bootAdmin();
        $main = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Main', 'type' => 'main']);
        $product = Product::create([
            'branch_id' => $branch->id, 'sku' => 'P1', 'name' => 'Botox 100u',
            'unit' => 'vial', 'selling_price' => 5000, 'cost_price' => 2500,
        ]);
        $supplier = Supplier::create(['branch_id' => $branch->id, 'name' => 'Test Supplier', 'is_active' => true]);

        $res = $this->postJson('/api/v1/inventory/receivings', [
            'warehouse_id' => $main->id,
            'supplier_id' => $supplier->id,
            'receive_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 2400,
                'lot_no' => 'BTX-2026A', 'expiry_date' => now()->addMonths(12)->toDateString(),
            ]],
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->assertNotEmpty($res->json('data.document_no'));
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $main->id,
            'quantity' => 5, 'lot_no' => 'BTX-2026A',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'warehouse_id' => $main->id,
            'type' => 'receive', 'quantity' => 5, 'before_qty' => 0, 'after_qty' => 5,
        ]);
    }

    public function test_requisition_approve_transfers_main_to_floor(): void
    {
        [$branch] = $this->bootAdmin();
        $main = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Main', 'type' => 'main']);
        $floor = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Floor', 'type' => 'floor']);
        $product = Product::create([
            'branch_id' => $branch->id, 'sku' => 'P2', 'name' => 'Lidocaine',
            'unit' => 'amp', 'selling_price' => 100, 'cost_price' => 50,
        ]);
        StockLevel::create([
            'product_id' => $product->id, 'warehouse_id' => $main->id,
            'quantity' => 50, 'lot_no' => 'LIDO-1', 'expiry_date' => now()->addMonths(8), 'cost_price' => 50,
        ]);

        $req = $this->postJson('/api/v1/inventory/requisitions', [
            'source_warehouse_id' => $main->id,
            'dest_warehouse_id' => $floor->id,
            'items' => [['product_id' => $product->id, 'requested_qty' => 10]],
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $reqId = $req->json('data.id');
        $this->postJson("/api/v1/inventory/requisitions/$reqId/approve", [], ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $main->id, 'quantity' => 40,
        ]);
        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $floor->id, 'quantity' => 10,
        ]);
    }

    public function test_checkout_with_product_item_deducts_floor_stock(): void
    {
        [$branch] = $this->bootAdmin();
        Warehouse::create(['branch_id' => $branch->id, 'name' => 'Main', 'type' => 'main']);
        $floor = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Floor', 'type' => 'floor']);
        $product = Product::create([
            'branch_id' => $branch->id, 'sku' => 'P3', 'name' => 'Vit C 1g',
            'unit' => 'amp', 'selling_price' => 200, 'cost_price' => 80,
        ]);
        StockLevel::create([
            'product_id' => $product->id, 'warehouse_id' => $floor->id,
            'quantity' => 20, 'lot_no' => 'VC-1', 'expiry_date' => now()->addMonths(10), 'cost_price' => 80,
        ]);

        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $vUuid = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
            ->json('data.id');
        $this->postJson("/api/v1/visits/$vUuid/invoice-items", [
            'item_type' => 'product', 'item_id' => $product->id, 'quantity' => 3,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $this->postJson("/api/v1/visits/$vUuid/checkout", [
            'payments' => [['method' => 'cash', 'amount' => 600]],
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id, 'warehouse_id' => $floor->id, 'quantity' => 17,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id, 'warehouse_id' => $floor->id,
            'type' => 'pos_deduct', 'quantity' => -3,
        ]);
    }

    public function test_checkout_blocks_when_floor_stock_is_only_expired(): void
    {
        [$branch] = $this->bootAdmin();
        Warehouse::create(['branch_id' => $branch->id, 'name' => 'Main', 'type' => 'main']);
        $floor = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Floor', 'type' => 'floor']);
        $product = Product::create([
            'branch_id' => $branch->id, 'sku' => 'P4', 'name' => 'Expired Drug',
            'unit' => 'tab', 'selling_price' => 50, 'cost_price' => 10,
            'block_dispensing_when_expired' => true,
        ]);
        StockLevel::create([
            'product_id' => $product->id, 'warehouse_id' => $floor->id,
            'quantity' => 10, 'lot_no' => 'OLD', 'expiry_date' => now()->subDays(1), 'cost_price' => 10,
        ]);

        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $vUuid = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
            ->json('data.id');
        $this->postJson("/api/v1/visits/$vUuid/invoice-items", [
            'item_type' => 'product', 'item_id' => $product->id, 'quantity' => 2,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $this->postJson("/api/v1/visits/$vUuid/checkout", [
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ], ['X-Branch-Id' => $branch->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.stock.0', 'สต็อกไม่พอ (ต้องการ 2, มีจริง 10)');
    }

    public function test_low_stock_endpoint_lists_products_below_reorder(): void
    {
        [$branch] = $this->bootAdmin();
        $floor = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Floor', 'type' => 'floor']);
        $low = Product::create([
            'branch_id' => $branch->id, 'sku' => 'LOW', 'name' => 'Low Item',
            'selling_price' => 10, 'cost_price' => 1, 'reorder_point' => 50, 'is_active' => true,
        ]);
        $ok = Product::create([
            'branch_id' => $branch->id, 'sku' => 'OK', 'name' => 'OK Item',
            'selling_price' => 10, 'cost_price' => 1, 'reorder_point' => 5, 'is_active' => true,
        ]);
        StockLevel::create(['product_id' => $low->id, 'warehouse_id' => $floor->id, 'quantity' => 5, 'cost_price' => 1]);
        StockLevel::create(['product_id' => $ok->id, 'warehouse_id' => $floor->id, 'quantity' => 100, 'cost_price' => 1]);

        $res = $this->getJson('/api/v1/inventory/low-stock', ['X-Branch-Id' => $branch->id])->assertOk();
        $rows = collect($res->json('data'))->keyBy('sku');
        $this->assertSame(45, $rows['LOW']['shortage']);
        $this->assertArrayNotHasKey('OK', $rows->toArray());
    }
}
