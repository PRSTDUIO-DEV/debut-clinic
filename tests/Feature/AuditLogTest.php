<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_creating_patient_writes_audit_log(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        $user->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/patients', [
            'first_name' => 'Audit', 'last_name' => 'Trail', 'gender' => 'male',
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $this->assertSame(1, AuditLog::query()->where('auditable_type', Patient::class)->where('action', 'create')->count());
    }

    public function test_audit_log_endpoint_requires_audit_permission(): void
    {
        $branch = Branch::factory()->create();
        $nurse = User::factory()->create(['branch_id' => $branch->id]);
        $nurse->branches()->attach($branch->id, ['is_primary' => true]);
        $nurse->roles()->attach(Role::where('name', 'nurse')->first()->id);
        Sanctum::actingAs($nurse);

        $this->getJson('/api/v1/audit-logs', ['X-Branch-Id' => $branch->id])
            ->assertStatus(403);
    }

    public function test_audit_log_does_not_store_password_field(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        $user->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/patients', [
            'first_name' => 'X', 'last_name' => 'Y', 'gender' => 'male',
        ], ['X-Branch-Id' => $branch->id]);

        $row = AuditLog::query()->latest('id')->first();
        $values = $row->new_values ?? [];
        $this->assertArrayNotHasKey('password', $values);
        $this->assertArrayNotHasKey('remember_token', $values);
    }
}
