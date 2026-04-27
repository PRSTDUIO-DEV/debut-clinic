<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Builder;

class ConflictDetector
{
    /**
     * Return list of conflicting appointment ids (excluding cancelled/no_show/completed)
     * for a given doctor or room slot.
     *
     * @return array<int>
     */
    public function findConflicts(
        int $branchId,
        int $doctorId,
        ?int $roomId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $ignoreId = null,
    ): array {
        $applyOverlap = function (Builder $q) use ($date, $startTime, $endTime) {
            $q->whereDate('appointment_date', $date)
                ->where(function ($w) use ($startTime, $endTime) {
                    $w->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
        };

        $base = Appointment::query()
            ->where('branch_id', $branchId)
            ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
            ->where(function ($q) use ($doctorId, $roomId, $applyOverlap) {
                $q->where(function ($qq) use ($doctorId, $applyOverlap) {
                    $qq->where('doctor_id', $doctorId);
                    $applyOverlap($qq);
                });
                if ($roomId !== null) {
                    $q->orWhere(function ($qq) use ($roomId, $applyOverlap) {
                        $qq->where('room_id', $roomId);
                        $applyOverlap($qq);
                    });
                }
            });

        if ($ignoreId !== null) {
            $base->where('id', '!=', $ignoreId);
        }

        return $base->pluck('id')->all();
    }

    /**
     * Get available slots for a doctor on a given date (operating hours 09:00-20:00 default).
     *
     * @return array<int,array{start:string,end:string}>
     */
    public function availableSlots(int $branchId, int $doctorId, string $date, int $slotMinutes = 30): array
    {
        $start = strtotime($date.' 09:00:00');
        $end = strtotime($date.' 20:00:00');

        $taken = Appointment::query()
            ->where('branch_id', $branchId)
            ->where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->get(['start_time', 'end_time'])
            ->map(fn ($a) => [strtotime($date.' '.$a->start_time), strtotime($date.' '.$a->end_time)])
            ->values()
            ->all();

        $slots = [];
        for ($t = $start; $t + ($slotMinutes * 60) <= $end; $t += $slotMinutes * 60) {
            $slotEnd = $t + ($slotMinutes * 60);
            $isFree = true;
            foreach ($taken as [$bs, $be]) {
                if ($t < $be && $slotEnd > $bs) {
                    $isFree = false;
                    break;
                }
            }
            if ($isFree) {
                $slots[] = [
                    'start' => date('H:i', $t),
                    'end' => date('H:i', $slotEnd),
                ];
            }
        }

        return $slots;
    }
}
