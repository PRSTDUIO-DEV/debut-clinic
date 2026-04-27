<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function bootSuperAdmin(): array
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->branches()->attach($branch->id, ['is_primary' => true]);
        $admin->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($admin);

        return [$branch, $admin];
    }

    private function bootBranchAdmin(): array
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->branches()->attach($branch->id, ['is_primary' => true]);
        $admin->roles()->attach(Role::where('name', 'branch_admin')->first()->id);
        Sanctum::actingAs($admin);

        return [$branch, $admin];
    }

    public function test_branches_crud_super_admin_only(): void
    {
        [$branch] = $this->bootSuperAdmin();

        $r = $this->postJson('/api/v1/admin/branches', [
            'name' => 'สาขา 2', 'code' => 'BR02',
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $newUuid = $r->json('data.uuid');
        $newId = $r->json('data.id');
        $this->putJson("/api/v1/admin/branches/{$newUuid}", ['name' => 'สาขา 2 (Renamed)'], ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.name', 'สาขา 2 (Renamed)');

        $this->deleteJson("/api/v1/admin/branches/{$newUuid}", [], ['X-Branch-Id' => $branch->id])->assertNoContent();
        $this->assertFalse((bool) Branch::find($newId)->is_active);
    }

    public function test_branches_blocked_for_branch_admin(): void
    {
        [$branch] = $this->bootBranchAdmin();

        $this->postJson('/api/v1/admin/branches', [
            'name' => 'X', 'code' => 'X',
        ], ['X-Branch-Id' => $branch->id])->assertStatus(403);
    }

    public function test_rooms_crud_with_position(): void
    {
        [$branch] = $this->bootSuperAdmin();

        $a = $this->postJson('/api/v1/admin/rooms', [
            'name' => 'Room A', 'type' => 'consultation', 'position' => 1,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $b = $this->postJson('/api/v1/admin/rooms', [
            'name' => 'Room B', 'position' => 0,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        // Should be sorted by position asc
        $list = $this->getJson('/api/v1/admin/rooms', ['X-Branch-Id' => $branch->id])
            ->assertOk()->json('data.data');
        $this->assertSame('Room B', $list[0]['name']);
        $this->assertSame('Room A', $list[1]['name']);

        $this->deleteJson('/api/v1/admin/rooms/'.$a->json('data.id'), [], ['X-Branch-Id' => $branch->id])
            ->assertNoContent();
        $this->assertNotNull(Room::withTrashed()->find($a->json('data.id'))->deleted_at);
    }

    public function test_banks_crud_validates_mdr(): void
    {
        [$branch] = $this->bootSuperAdmin();

        $this->postJson('/api/v1/admin/banks', [
            'name' => 'KBANK', 'mdr_rate' => 150,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(422);

        $this->postJson('/api/v1/admin/banks', [
            'name' => 'KBANK', 'account_no' => '123', 'mdr_rate' => 1.5,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->assertSame(1, Bank::where('branch_id', $branch->id)->count());
    }

    public function test_customer_groups_crud_with_color_and_discount(): void
    {
        [$branch] = $this->bootSuperAdmin();

        $r = $this->postJson('/api/v1/admin/customer-groups', [
            'name' => 'VIP', 'discount_rate' => 15, 'color' => '#f59e0b', 'icon' => '💎',
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $this->assertSame('#f59e0b', $r->json('data.color'));
        $this->assertSame('💎', $r->json('data.icon'));
    }

    public function test_suppliers_crud_full_contact(): void
    {
        [$branch] = $this->bootSuperAdmin();

        $r = $this->postJson('/api/v1/admin/suppliers', [
            'name' => 'Acme', 'contact_person' => 'คุณเอ', 'phone' => '021234567',
            'email' => 'a@acme.test', 'tax_id' => '1234567890123',
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->putJson('/api/v1/admin/suppliers/'.$r->json('data.id'), [
            'payment_terms' => 'NET-30',
        ], ['X-Branch-Id' => $branch->id])->assertOk()
            ->assertJsonPath('data.payment_terms', 'NET-30');
    }

    public function test_procedures_crud_validates_unique_code(): void
    {
        [$branch] = $this->bootSuperAdmin();

        $this->postJson('/api/v1/admin/procedures', [
            'code' => 'BTX001', 'name' => 'Botox', 'price' => 5000,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->postJson('/api/v1/admin/procedures', [
            'code' => 'BTX001', 'name' => 'Duplicate', 'price' => 1,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(422);
    }

    public function test_products_crud_with_category(): void
    {
        [$branch] = $this->bootSuperAdmin();

        $cat = $this->postJson('/api/v1/admin/product-categories', [
            'name' => 'Skincare', 'commission_rate' => 5,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->postJson('/api/v1/admin/products', [
            'sku' => 'SKU001', 'name' => 'Cream X',
            'category_id' => $cat->json('data.id'),
            'selling_price' => 1500, 'cost_price' => 500,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $list = $this->getJson('/api/v1/admin/products?category_id='.$cat->json('data.id'), ['X-Branch-Id' => $branch->id])
            ->assertOk()->json('data.data');
        $this->assertCount(1, $list);
    }

    public function test_expense_categories_crud(): void
    {
        [$branch] = $this->bootSuperAdmin();

        $this->postJson('/api/v1/admin/expense-categories', [
            'name' => 'ค่าน้ำค่าไฟ', 'color' => '#dc2626', 'icon' => '⚡',
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->assertSame(1, ExpenseCategory::where('branch_id', $branch->id)->count());
    }

    public function test_reorder_endpoint_updates_position(): void
    {
        [$branch] = $this->bootSuperAdmin();

        $a = Room::create(['branch_id' => $branch->id, 'name' => 'A', 'is_active' => true, 'position' => 0]);
        $b = Room::create(['branch_id' => $branch->id, 'name' => 'B', 'is_active' => true, 'position' => 0]);
        $c = Room::create(['branch_id' => $branch->id, 'name' => 'C', 'is_active' => true, 'position' => 0]);

        $this->postJson('/api/v1/admin/rooms/reorder', [
            'order' => [$c->id, $a->id, $b->id],
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $this->assertSame(0, Room::find($c->id)->position);
        $this->assertSame(1, Room::find($a->id)->position);
        $this->assertSame(2, Room::find($b->id)->position);
    }

    public function test_settings_view_permission_required(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        // doctor role doesn't have settings.view
        $user->roles()->attach(Role::where('name', 'doctor')->first()->id);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/rooms', ['X-Branch-Id' => $branch->id])->assertStatus(403);
    }
}
