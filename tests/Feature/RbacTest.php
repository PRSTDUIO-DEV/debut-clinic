<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_super_admin_can_access_admin_endpoint(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        $user->roles()->attach(Role::where('name', 'super_admin')->first()->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/roles')
            ->assertOk()
            ->assertJsonStructure(['data' => ['roles', 'permissions']]);
    }

    public function test_role_without_permission_is_blocked_from_admin_endpoint(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        $user->roles()->attach(Role::where('name', 'nurse')->first()->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/roles')
            ->assertStatus(403)
            ->assertJsonPath('code', 'forbidden');
    }
}
