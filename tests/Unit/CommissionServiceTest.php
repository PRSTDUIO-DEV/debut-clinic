<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\CommissionRate;
use App\Models\Procedure;
use App\Models\User;
use App\Services\CommissionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function bootEnv(): array
    {
        $branch = Branch::factory()->create();
        $proc = Procedure::factory()->create([
            'branch_id' => $branch->id,
            'doctor_fee_rate' => 30,
            'staff_commission_rate' => 5,
        ]);
        $doctor = User::factory()->create(['branch_id' => $branch->id, 'is_doctor' => true]);

        return [$branch, $proc, $doctor];
    }

    public function test_resolve_falls_back_to_procedure_default_when_no_rates_defined(): void
    {
        [$branch, $proc, $doctor] = $this->bootEnv();
        $svc = $this->app->make(CommissionService::class);

        $resolved = $svc->resolveRate($branch->id, 'doctor_fee', $doctor->id, $proc);
        $this->assertSame(30.0, $resolved['rate']);
        $this->assertSame('procedure.doctor_fee_rate', $resolved['source']);
    }

    public function test_user_specific_procedure_rate_takes_priority(): void
    {
        [$branch, $proc, $doctor] = $this->bootEnv();
        // Generic procedure rate
        CommissionRate::create([
            'branch_id' => $branch->id, 'type' => 'doctor_fee',
            'applicable_type' => 'procedure', 'applicable_id' => $proc->id,
            'user_id' => null, 'rate' => 25, 'is_active' => true,
        ]);
        // User-specific override
        CommissionRate::create([
            'branch_id' => $branch->id, 'type' => 'doctor_fee',
            'applicable_type' => 'procedure', 'applicable_id' => $proc->id,
            'user_id' => $doctor->id, 'rate' => 40, 'is_active' => true,
        ]);

        $svc = $this->app->make(CommissionService::class);
        $resolved = $svc->resolveRate($branch->id, 'doctor_fee', $doctor->id, $proc);
        $this->assertSame(40.0, $resolved['rate']);
    }

    public function test_calculate_uses_fixed_amount_when_provided(): void
    {
        $svc = $this->app->make(CommissionService::class);
        $this->assertSame(500.0, $svc->calculate(['rate' => 99, 'fixed_amount' => 500.0, 'source' => 'x'], 10000));
        $this->assertSame(300.0, $svc->calculate(['rate' => 30, 'fixed_amount' => null, 'source' => 'x'], 1000));
        $this->assertSame(0.0, $svc->calculate(['rate' => 0, 'fixed_amount' => null, 'source' => 'x'], 1000));
    }

    public function test_resolve_skips_inactive_rates(): void
    {
        [$branch, $proc, $doctor] = $this->bootEnv();
        CommissionRate::create([
            'branch_id' => $branch->id, 'type' => 'doctor_fee',
            'applicable_type' => 'procedure', 'applicable_id' => $proc->id,
            'user_id' => $doctor->id, 'rate' => 50, 'is_active' => false,
        ]);

        $svc = $this->app->make(CommissionService::class);
        $resolved = $svc->resolveRate($branch->id, 'doctor_fee', $doctor->id, $proc);
        // falls back to procedure default
        $this->assertSame(30.0, $resolved['rate']);
    }
}
