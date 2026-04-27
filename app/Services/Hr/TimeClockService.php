<?php

namespace App\Services\Hr;

use App\Models\Branch;
use App\Models\TimeClock;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeClockService
{
    /**
     * Standard work day: 09:00–18:00 (9h with 1h lunch = 8h work).
     * Late if clock-in > 09:15. Overtime if clock-out > 18:30.
     */
    public const SHIFT_START = '09:00';

    public const SHIFT_END = '18:00';

    public const LATE_GRACE_MIN = 15;

    public const OT_GRACE_MIN = 30;

    public function clockIn(User $user, Branch $branch, string $source = 'pin'): TimeClock
    {
        return DB::transaction(function () use ($user, $branch, $source) {
            $open = TimeClock::where('user_id', $user->id)
                ->whereNull('clock_out')
                ->lockForUpdate()
                ->first();
            if ($open) {
                throw ValidationException::withMessages(['clock' => 'พนักงานยังไม่ได้ Clock-out จากบันทึกก่อน']);
            }

            $now = Carbon::now();
            $shiftStart = $now->copy()->setTimeFromTimeString(self::SHIFT_START);
            $late = 0;
            if ($now->gt($shiftStart->copy()->addMinutes(self::LATE_GRACE_MIN))) {
                $late = $now->diffInMinutes($shiftStart, false);
                $late = abs((int) round($late));
            }

            return TimeClock::create([
                'user_id' => $user->id,
                'branch_id' => $branch->id,
                'clock_in' => $now,
                'late_minutes' => $late,
                'source' => $source,
            ]);
        });
    }

    public function clockOut(User $user): TimeClock
    {
        return DB::transaction(function () use ($user) {
            $open = TimeClock::where('user_id', $user->id)
                ->whereNull('clock_out')
                ->orderByDesc('clock_in')
                ->lockForUpdate()
                ->first();
            if (! $open) {
                throw ValidationException::withMessages(['clock' => 'ไม่พบบันทึก Clock-in ที่ยังเปิดอยู่']);
            }

            $now = Carbon::now();
            $clockIn = Carbon::parse($open->clock_in);
            $totalMin = abs((int) round($clockIn->diffInMinutes($now, false)));

            $shiftEnd = $now->copy()->setTimeFromTimeString(self::SHIFT_END);
            $ot = 0;
            if ($now->gt($shiftEnd->copy()->addMinutes(self::OT_GRACE_MIN))) {
                $ot = abs((int) round($shiftEnd->diffInMinutes($now, false)));
            }

            $open->clock_out = $now;
            $open->total_minutes = $totalMin;
            $open->overtime_minutes = $ot;
            $open->save();

            return $open;
        });
    }

    public function manualEntry(User $user, Branch $branch, string $clockIn, string $clockOut, ?string $reason, User $approver): TimeClock
    {
        $in = Carbon::parse($clockIn);
        $out = Carbon::parse($clockOut);
        if ($out->lte($in)) {
            throw ValidationException::withMessages(['clock_out' => 'clock_out ต้องมากกว่า clock_in']);
        }
        $totalMin = abs((int) round($in->diffInMinutes($out, false)));

        return TimeClock::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'clock_in' => $in,
            'clock_out' => $out,
            'total_minutes' => $totalMin,
            'source' => 'manual',
            'approved_by' => $approver->id,
            'notes' => $reason,
        ]);
    }

    public function currentOpen(User $user): ?TimeClock
    {
        return TimeClock::where('user_id', $user->id)
            ->whereNull('clock_out')
            ->orderByDesc('clock_in')
            ->first();
    }

    public function dailySummary(User $user, string $date): array
    {
        $rows = TimeClock::where('user_id', $user->id)
            ->whereDate('clock_in', $date)
            ->get();
        $total = (int) $rows->sum('total_minutes');
        $late = (int) $rows->sum('late_minutes');
        $ot = (int) $rows->sum('overtime_minutes');

        return [
            'date' => $date,
            'punches' => $rows->count(),
            'total_minutes' => $total,
            'total_hours' => round($total / 60, 2),
            'late_minutes' => $late,
            'overtime_minutes' => $ot,
        ];
    }

    public function monthlySummary(int $userId, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $rows = TimeClock::where('user_id', $userId)
            ->whereBetween('clock_in', [$start, $end])
            ->whereNotNull('clock_out')
            ->get();

        $totalMin = (int) $rows->sum('total_minutes');
        $otMin = (int) $rows->sum('overtime_minutes');
        $lateCount = $rows->where('late_minutes', '>', 0)->count();
        $daysWorked = $rows->groupBy(fn ($r) => Carbon::parse($r->clock_in)->toDateString())->count();

        return [
            'period' => $start->format('Y-m'),
            'total_minutes' => $totalMin,
            'total_hours' => round($totalMin / 60, 2),
            'overtime_hours' => round($otMin / 60, 2),
            'days_worked' => $daysWorked,
            'late_count' => $lateCount,
            'punches' => $rows->count(),
        ];
    }
}
