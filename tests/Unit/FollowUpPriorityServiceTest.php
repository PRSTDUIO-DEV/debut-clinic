<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\FollowUp;
use App\Models\Patient;
use App\Services\FollowUpPriorityService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpPriorityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function makeFu(string $date, string $priority = 'normal', ?string $notes = null): FollowUp
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        return FollowUp::create([
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'follow_up_date' => $date,
            'priority' => $priority,
            'status' => 'pending',
            'notes' => $notes,
        ]);
    }

    public function test_classify_overdue_eight_days_is_critical(): void
    {
        $svc = $this->app->make(FollowUpPriorityService::class);
        $f = $this->makeFu(now()->subDays(8)->toDateString(), 'normal');
        $this->assertSame('critical', $svc->classify($f));
    }

    public function test_classify_overdue_four_days_is_high(): void
    {
        $svc = $this->app->make(FollowUpPriorityService::class);
        $f = $this->makeFu(now()->subDays(4)->toDateString(), 'normal');
        $this->assertSame('high', $svc->classify($f));
    }

    public function test_classify_today_is_normal(): void
    {
        $svc = $this->app->make(FollowUpPriorityService::class);
        $f = $this->makeFu(now()->toDateString(), 'normal');
        $this->assertSame('normal', $svc->classify($f));
    }

    public function test_classify_far_future_is_low(): void
    {
        $svc = $this->app->make(FollowUpPriorityService::class);
        $f = $this->makeFu(now()->addDays(45)->toDateString(), 'normal');
        $this->assertSame('low', $svc->classify($f));
    }

    public function test_classify_with_critical_tag_in_notes_overrides_to_critical(): void
    {
        $svc = $this->app->make(FollowUpPriorityService::class);
        $f = $this->makeFu(now()->toDateString(), 'normal', '[critical] severe reaction reported');
        $this->assertSame('critical', $svc->classify($f));
    }

    public function test_recalculate_all_updates_changed_rows(): void
    {
        $svc = $this->app->make(FollowUpPriorityService::class);
        $f1 = $this->makeFu(now()->subDays(10)->toDateString(), 'normal');
        $f2 = $this->makeFu(now()->toDateString(), 'low');

        $changed = $svc->recalculateAll();
        $this->assertSame(2, $changed);

        $this->assertSame('critical', $f1->fresh()->priority);
        $this->assertSame('normal', $f2->fresh()->priority);
    }
}
