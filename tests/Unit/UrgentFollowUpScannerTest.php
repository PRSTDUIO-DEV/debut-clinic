<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\FollowUp;
use App\Models\FollowUpRule;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\UrgentFollowUpScanner;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UrgentFollowUpScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_overdue_days_rule_promotes_priority_and_writes_notification(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $branchAdminRole = Role::query()->where('name', 'branch_admin')->firstOrFail();
        $admin->roles()->attach($branchAdminRole->id);

        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $followUp = FollowUp::create([
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'follow_up_date' => Carbon::today()->subDays(5),
            'priority' => 'normal',
            'status' => 'pending',
        ]);

        $rule = FollowUpRule::create([
            'branch_id' => $branch->id,
            'name' => 'เลย 3 วัน',
            'priority' => 'high',
            'condition_type' => 'overdue_days',
            'condition_value' => ['days' => 3],
            'notify_branch_admin' => true,
            'preferred_channel' => 'in_app',
            'is_active' => true,
        ]);

        $written = $this->app->make(UrgentFollowUpScanner::class)->run($branch->id);

        $this->assertGreaterThanOrEqual(1, $written);
        $this->assertSame('high', $followUp->fresh()->priority);
        $this->assertSame(1, Notification::query()
            ->where('type', 'urgent_followup')
            ->where('recipient_id', $admin->id)
            ->count());
    }

    public function test_inactive_rule_does_nothing(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        FollowUp::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'follow_up_date' => Carbon::today()->subDays(10),
            'priority' => 'normal', 'status' => 'pending',
        ]);
        FollowUpRule::create([
            'branch_id' => $branch->id, 'name' => 'r', 'priority' => 'high',
            'condition_type' => 'overdue_days', 'condition_value' => ['days' => 3],
            'notify_branch_admin' => true, 'preferred_channel' => 'in_app',
            'is_active' => false,
        ]);

        $written = $this->app->make(UrgentFollowUpScanner::class)->run($branch->id);
        $this->assertSame(0, $written);
    }
}
