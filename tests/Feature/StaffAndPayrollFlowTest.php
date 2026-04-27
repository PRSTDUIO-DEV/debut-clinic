<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\TimeClock;
use App\Models\User;
use App\Services\Accounting\ChartOfAccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffAndPayrollFlowTest extends TestCase
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
        app(ChartOfAccountSeeder::class)->seed($branch->id);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->branches()->attach($branch->id, ['is_primary' => true]);
        $admin->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($admin);

        return [$branch, $admin];
    }

    public function test_create_staff_with_pin_and_assign_roles(): void
    {
        [$branch] = $this->bootAdmin();
        $doctor = Role::where('name', 'doctor')->first();

        $r = $this->postJson('/api/v1/admin/staff', [
            'name' => 'หมอเอ',
            'email' => 'doctora@test.com',
            'employee_code' => 'EMP001',
            'password' => 'Password123!',
            'pin' => '1234',
            'is_doctor' => true,
            'role_ids' => [$doctor->id],
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $userId = $r->json('data.id');
        $user = User::find($userId);
        $this->assertTrue(Hash::check('1234', $user->pin_hash));
        $this->assertTrue($user->roles()->where('roles.name', 'doctor')->exists());
        $this->assertTrue($user->branches()->where('branches.id', $branch->id)->exists());
    }

    public function test_pin_kiosk_clock_in_and_out_flow(): void
    {
        [$branch] = $this->bootAdmin();
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'employee_code' => 'EMP100',
            'pin_hash' => Hash::make('5678'),
            'is_active' => true,
        ]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);

        // Wrong PIN → 422
        $this->postJson('/api/v1/public/time-clock/in', [
            'employee_code' => 'EMP100', 'pin' => '0000', 'branch_id' => $branch->id,
        ])->assertStatus(422);

        // Correct PIN clock-in
        $this->postJson('/api/v1/public/time-clock/in', [
            'employee_code' => 'EMP100', 'pin' => '5678', 'branch_id' => $branch->id,
        ])->assertCreated()->assertJsonPath('data.action', 'in');

        // Clock-out
        $this->postJson('/api/v1/public/time-clock/out', [
            'employee_code' => 'EMP100', 'pin' => '5678', 'branch_id' => $branch->id,
        ])->assertCreated()->assertJsonPath('data.action', 'out');

        $this->assertSame(1, TimeClock::where('user_id', $user->id)->whereNotNull('clock_out')->count());
    }

    public function test_payroll_full_flow_preview_finalize_mark_paid(): void
    {
        [$branch, $admin] = $this->bootAdmin();
        $emp = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $emp->branches()->attach($branch->id, ['is_primary' => true]);

        // Setup compensation
        $this->postJson("/api/v1/admin/staff/{$emp->uuid}/compensation-rules", [
            'type' => 'monthly', 'base_amount' => 25000,
            'valid_from' => now()->startOfMonth()->toDateString(),
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        // Preview
        $r = $this->postJson('/api/v1/admin/payrolls/preview', [
            'year' => (int) now()->format('Y'), 'month' => (int) now()->format('m'),
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $payrollId = $r->json('data.id');
        $items = $r->json('data.items');
        // Both admin + emp are active in branch → 2 items
        $this->assertGreaterThanOrEqual(2, count($items));

        // Finalize
        $this->postJson("/api/v1/admin/payrolls/{$payrollId}/finalize", [], ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized');

        // Mark paid
        $this->postJson("/api/v1/admin/payrolls/{$payrollId}/mark-paid", [
            'payment_method' => 'transfer', 'payment_reference' => 'BANK-001',
        ], ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_employee_profile_create_or_update(): void
    {
        [$branch] = $this->bootAdmin();
        $emp = User::factory()->create(['branch_id' => $branch->id]);

        $this->putJson("/api/v1/admin/staff/{$emp->uuid}/profile", [
            'employee_no' => 'EMP-X-001',
            'position' => 'พยาบาล',
            'hire_date' => '2024-01-15',
            'bank_name' => 'KBANK',
            'bank_account' => '123-456-7890',
        ], ['X-Branch-Id' => $branch->id])->assertOk()
            ->assertJsonPath('data.employee_no', 'EMP-X-001')
            ->assertJsonPath('data.position', 'พยาบาล');

        $this->putJson("/api/v1/admin/staff/{$emp->uuid}/profile", [
            'position' => 'หัวหน้าพยาบาล',
        ], ['X-Branch-Id' => $branch->id])->assertOk()
            ->assertJsonPath('data.position', 'หัวหน้าพยาบาล');

        $this->assertSame(1, EmployeeProfile::where('user_id', $emp->id)->count());
    }
}
