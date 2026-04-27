<?php

namespace Tests\Unit;

use App\Models\BirthdayCampaign;
use App\Models\Branch;
use App\Models\Notification;
use App\Models\Patient;
use App\Services\BirthdayCampaignService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BirthdayCampaignServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_runs_campaign_writes_notifications_at_offsets(): void
    {
        Carbon::setTestNow('2026-04-26 09:00:00');
        $today = Carbon::today();

        $branch = Branch::factory()->create();
        // birthday today
        Patient::factory()->create(['branch_id' => $branch->id, 'date_of_birth' => $today->copy()->subYears(30)]);
        // birthday in 7 days
        Patient::factory()->create(['branch_id' => $branch->id, 'date_of_birth' => $today->copy()->addDays(7)->subYears(25)]);
        // birthday in 30 days
        Patient::factory()->create(['branch_id' => $branch->id, 'date_of_birth' => $today->copy()->addDays(30)->subYears(40)]);
        // birthday yesterday (should NOT trigger any offset)
        Patient::factory()->create(['branch_id' => $branch->id, 'date_of_birth' => $today->copy()->subDays(1)->subYears(35)]);
        // birthday 3 days ago (should trigger +3)
        Patient::factory()->create(['branch_id' => $branch->id, 'date_of_birth' => $today->copy()->subDays(3)->subYears(45)]);

        $campaign = BirthdayCampaign::create([
            'branch_id' => $branch->id,
            'name' => 'Default',
            'templates' => [
                '30' => ['channel' => 'in_app', 'title' => '30 day', 'body' => 'hi'],
                '7' => ['channel' => 'in_app', 'title' => '7 day', 'body' => 'hi'],
                '0' => ['channel' => 'in_app', 'title' => 'today', 'body' => 'hi'],
                '+3' => ['channel' => 'in_app', 'title' => '+3 day', 'body' => 'hi'],
            ],
            'is_active' => true,
        ]);

        $written = $this->app->make(BirthdayCampaignService::class)->runCampaign($campaign);

        // 4 patients trigger 4 birthday notifications + 1 follow-up reminder for +3 offset
        $this->assertGreaterThanOrEqual(4, $written);
        $this->assertSame(4, Notification::query()->where('type', 'birthday')->count());

        Carbon::setTestNow();
    }

    public function test_idempotent_within_same_day(): void
    {
        Carbon::setTestNow('2026-04-26 09:00:00');
        $branch = Branch::factory()->create();
        Patient::factory()->create(['branch_id' => $branch->id, 'date_of_birth' => Carbon::today()->subYears(30)]);

        $campaign = BirthdayCampaign::create([
            'branch_id' => $branch->id,
            'name' => 'Default',
            'templates' => ['0' => ['channel' => 'in_app', 'title' => 'X', 'body' => 'Y']],
            'is_active' => true,
        ]);

        $svc = $this->app->make(BirthdayCampaignService::class);
        $svc->runCampaign($campaign);
        $second = $svc->runCampaign($campaign->fresh());

        $this->assertSame(0, $second);
        $this->assertSame(1, Notification::query()->where('type', 'birthday')->count());

        Carbon::setTestNow();
    }
}
