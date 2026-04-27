<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_super_admin_has_any_permission(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $user->roles()->attach(Role::where('name', 'super_admin')->first()->id);

        $svc = $this->app->make(PermissionService::class);

        $this->assertTrue($svc->userHas($user, 'patients.view'));
        $this->assertTrue($svc->userHas($user, 'inventory.adjust'));
        $this->assertTrue($svc->userHas($user, 'finance.daily_pl.view'));
    }

    public function test_specific_role_only_has_assigned_permissions(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $user->roles()->attach(Role::where('name', 'pharmacist')->first()->id);

        $svc = $this->app->make(PermissionService::class);

        $this->assertTrue($svc->userHas($user, 'inventory.view'));
        $this->assertTrue($svc->userHas($user, 'patients.view'));
        $this->assertFalse($svc->userHas($user, 'finance.daily_pl.view'));
        $this->assertFalse($svc->userHas($user, 'roles.manage'));
    }

    public function test_inactive_user_has_no_permissions_even_with_super_admin_role(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => false]);
        $user->roles()->attach(Role::where('name', 'super_admin')->first()->id);

        $svc = $this->app->make(PermissionService::class);

        $this->assertFalse($svc->userHas($user, 'patients.view'));
    }
}
