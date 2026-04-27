<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\BroadcastSegment;
use App\Models\Course;
use App\Models\CustomerGroup;
use App\Models\MemberAccount;
use App\Models\Patient;
use App\Services\SegmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SegmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSegment(Branch $branch, array $rules): BroadcastSegment
    {
        return BroadcastSegment::create([
            'branch_id' => $branch->id, 'name' => 'test', 'rules' => $rules, 'is_active' => true,
        ]);
    }

    public function test_filter_by_customer_group_ids(): void
    {
        $branch = Branch::factory()->create();
        $vip = CustomerGroup::create(['branch_id' => $branch->id, 'name' => 'VIP', 'discount_rate' => 10, 'is_active' => true]);
        $normal = CustomerGroup::create(['branch_id' => $branch->id, 'name' => 'Normal', 'discount_rate' => 0, 'is_active' => true]);

        Patient::factory()->create(['branch_id' => $branch->id, 'customer_group_id' => $vip->id]);
        Patient::factory()->create(['branch_id' => $branch->id, 'customer_group_id' => $vip->id]);
        Patient::factory()->create(['branch_id' => $branch->id, 'customer_group_id' => $normal->id]);

        $seg = $this->makeSegment($branch, ['customer_group_ids' => [$vip->id]]);
        $this->assertSame(2, app(SegmentService::class)->count($seg));
    }

    public function test_filter_by_last_visit_days_min_includes_dormant_and_never(): void
    {
        $branch = Branch::factory()->create();
        Patient::factory()->create(['branch_id' => $branch->id, 'last_visit_at' => Carbon::now()->subDays(60)]); // dormant
        Patient::factory()->create(['branch_id' => $branch->id, 'last_visit_at' => Carbon::now()->subDays(5)]);  // recent (excluded)
        Patient::factory()->create(['branch_id' => $branch->id, 'last_visit_at' => null]);                       // never

        $seg = $this->makeSegment($branch, ['last_visit_days_min' => 30]);
        $this->assertSame(2, app(SegmentService::class)->count($seg));
    }

    public function test_filter_by_total_spent_min(): void
    {
        $branch = Branch::factory()->create();
        Patient::factory()->create(['branch_id' => $branch->id, 'total_spent' => 50000]);
        Patient::factory()->create(['branch_id' => $branch->id, 'total_spent' => 5000]);

        $seg = $this->makeSegment($branch, ['total_spent_min' => 10000]);
        $this->assertSame(1, app(SegmentService::class)->count($seg));
    }

    public function test_filter_by_has_member_account(): void
    {
        $branch = Branch::factory()->create();
        $p1 = Patient::factory()->create(['branch_id' => $branch->id]);
        Patient::factory()->create(['branch_id' => $branch->id]);

        MemberAccount::create([
            'branch_id' => $branch->id, 'patient_id' => $p1->id,
            'balance' => 10000, 'total_deposit' => 10000, 'total_used' => 0, 'status' => 'active',
        ]);

        $seg = $this->makeSegment($branch, ['has_member_account' => true]);
        $this->assertSame(1, app(SegmentService::class)->count($seg));
    }

    public function test_filter_by_has_active_course(): void
    {
        $branch = Branch::factory()->create();
        $p1 = Patient::factory()->create(['branch_id' => $branch->id]);
        Patient::factory()->create(['branch_id' => $branch->id]);

        Course::create([
            'branch_id' => $branch->id, 'patient_id' => $p1->id,
            'name' => 'Pkg', 'total_sessions' => 5, 'used_sessions' => 0, 'remaining_sessions' => 5,
            'status' => 'active',
        ]);

        $seg = $this->makeSegment($branch, ['has_active_course' => true]);
        $this->assertSame(1, app(SegmentService::class)->count($seg));
    }

    public function test_filter_by_age_range(): void
    {
        $branch = Branch::factory()->create();
        Patient::factory()->create(['branch_id' => $branch->id, 'date_of_birth' => Carbon::now()->subYears(25)]);
        Patient::factory()->create(['branch_id' => $branch->id, 'date_of_birth' => Carbon::now()->subYears(45)]);
        Patient::factory()->create(['branch_id' => $branch->id, 'date_of_birth' => Carbon::now()->subYears(70)]);

        $seg = $this->makeSegment($branch, ['age_min' => 30, 'age_max' => 60]);
        $this->assertSame(1, app(SegmentService::class)->count($seg));
    }

    public function test_touch_stats_updates_count_and_timestamp(): void
    {
        $branch = Branch::factory()->create();
        Patient::factory()->count(3)->create(['branch_id' => $branch->id]);
        $seg = $this->makeSegment($branch, []);

        $svc = app(SegmentService::class);
        $svc->touchStats($seg);
        $seg->refresh();

        $this->assertSame(3, $seg->last_resolved_count);
        $this->assertNotNull($seg->last_resolved_at);
    }
}
