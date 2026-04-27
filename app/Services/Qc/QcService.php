<?php

namespace App\Services\Qc;

use App\Models\QcChecklist;
use App\Models\QcChecklistItem;
use App\Models\QcRun;
use App\Models\QcRunItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QcService
{
    /**
     * Start a new run (or return existing in_progress run for the same date+checklist).
     */
    public function startRun(QcChecklist $checklist, User $user, ?string $date = null): QcRun
    {
        $date = $date ?: Carbon::today()->toDateString();

        return DB::transaction(function () use ($checklist, $user, $date) {
            $existing = QcRun::where('checklist_id', $checklist->id)
                ->where('branch_id', $checklist->branch_id)
                ->whereDate('run_date', $date)
                ->whereIn('status', ['pending', 'in_progress'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ($existing->status === 'pending') {
                    $existing->status = 'in_progress';
                    $existing->performed_by = $user->id;
                    $existing->save();
                }

                return $existing;
            }

            return QcRun::create([
                'checklist_id' => $checklist->id,
                'branch_id' => $checklist->branch_id,
                'run_date' => $date,
                'status' => 'in_progress',
                'performed_by' => $user->id,
                'total_items' => $checklist->items()->count(),
            ]);
        });
    }

    /**
     * Record a single item result. Re-recording overrides previous value.
     */
    public function recordItem(QcRun $run, QcChecklistItem $item, string $status, ?string $note = null, ?string $photoPath = null): QcRunItem
    {
        if (! in_array($status, ['pass', 'fail', 'na'])) {
            throw ValidationException::withMessages(['status' => 'must be pass | fail | na']);
        }
        if ($run->status === 'completed') {
            throw ValidationException::withMessages(['run' => 'Run already completed']);
        }

        $row = QcRunItem::updateOrCreate(
            ['run_id' => $run->id, 'item_id' => $item->id],
            [
                'status' => $status,
                'note' => $note,
                'photo_path' => $photoPath,
                'recorded_at' => now(),
            ],
        );

        if ($run->status === 'pending') {
            $run->status = 'in_progress';
            $run->save();
        }

        return $row;
    }

    public function completeRun(QcRun $run): QcRun
    {
        return DB::transaction(function () use ($run) {
            $run = QcRun::lockForUpdate()->find($run->id);
            if ($run->status === 'completed') {
                return $run;
            }
            $items = $run->items()->get();
            $run->total_items = $items->count();
            $run->passed_count = $items->where('status', 'pass')->count();
            $run->failed_count = $items->where('status', 'fail')->count();
            $run->na_count = $items->where('status', 'na')->count();
            $run->status = 'completed';
            $run->completed_at = now();
            $run->save();

            return $run;
        });
    }

    /**
     * Aggregate summary for a date range — runs count, pass rate, fail items count.
     *
     * @return array{runs:int, completed:int, total_items:int, passed:int, failed:int, na:int, pass_rate_pct:float}
     */
    public function summary(int $branchId, string $from, string $to): array
    {
        $runs = QcRun::where('branch_id', $branchId)
            ->whereDate('run_date', '>=', $from)
            ->whereDate('run_date', '<=', $to)
            ->get();
        $completed = $runs->where('status', 'completed');
        $total = $completed->sum('total_items');
        $passed = $completed->sum('passed_count');
        $failed = $completed->sum('failed_count');
        $na = $completed->sum('na_count');
        $rate = $total > 0 ? round($passed / $total * 100, 2) : 0;

        return [
            'runs' => $runs->count(),
            'completed' => $completed->count(),
            'total_items' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'na' => $na,
            'pass_rate_pct' => $rate,
        ];
    }
}
