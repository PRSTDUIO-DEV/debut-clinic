<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\TimeClock;
use App\Models\User;
use App\Services\Hr\TimeClockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TimeClockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_clock_in_creates_record_and_detects_late(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 27, 9, 30));
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $row = app(TimeClockService::class)->clockIn($user, $branch);
        $this->assertNotNull($row->clock_in);
        $this->assertGreaterThan(0, $row->late_minutes); // 09:30 > 09:15 grace
    }

    public function test_clock_in_within_grace_is_not_late(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 27, 9, 10));
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $row = app(TimeClockService::class)->clockIn($user, $branch);
        $this->assertSame(0, $row->late_minutes);
    }

    public function test_clock_out_computes_total_and_overtime(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 27, 9, 0));
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        app(TimeClockService::class)->clockIn($user, $branch);

        Carbon::setTestNow(Carbon::create(2026, 4, 27, 19, 30));
        $row = app(TimeClockService::class)->clockOut($user);

        $this->assertSame(630, $row->total_minutes); // 9h 30m work
        $this->assertGreaterThan(0, $row->overtime_minutes); // 19:30 > 18:30 grace
    }

    public function test_double_clock_in_is_blocked(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        app(TimeClockService::class)->clockIn($user, $branch);

        $this->expectException(ValidationException::class);
        app(TimeClockService::class)->clockIn($user, $branch);
    }

    public function test_clock_out_without_open_record_fails(): void
    {
        $user = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(TimeClockService::class)->clockOut($user);
    }

    public function test_monthly_summary_aggregates_correctly(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        TimeClock::create([
            'user_id' => $user->id, 'branch_id' => $branch->id,
            'clock_in' => '2026-04-01 09:00:00', 'clock_out' => '2026-04-01 18:00:00',
            'total_minutes' => 540, 'late_minutes' => 0, 'overtime_minutes' => 0, 'source' => 'pin',
        ]);
        TimeClock::create([
            'user_id' => $user->id, 'branch_id' => $branch->id,
            'clock_in' => '2026-04-02 09:30:00', 'clock_out' => '2026-04-02 18:30:00',
            'total_minutes' => 540, 'late_minutes' => 30, 'overtime_minutes' => 0, 'source' => 'pin',
        ]);

        $sum = app(TimeClockService::class)->monthlySummary($user->id, 2026, 4);
        $this->assertSame(1080, $sum['total_minutes']);
        $this->assertSame(18.0, $sum['total_hours']);
        $this->assertSame(2, $sum['days_worked']);
        $this->assertSame(1, $sum['late_count']);
    }
}
