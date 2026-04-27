<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseUsage;
use App\Models\InvoiceItem;
use App\Models\Procedure;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseService
{
    /**
     * Create a Course from a paid invoice item if its procedure is a package.
     * Returns the course (or null if not a package).
     */
    public function purchaseFromInvoiceItem(InvoiceItem $item, int $patientId, int $branchId): ?Course
    {
        if ($item->item_type !== 'procedure' || empty($item->item_id)) {
            return null;
        }
        $proc = Procedure::query()->find($item->item_id);
        if (! $proc || ! $proc->is_package || $proc->package_sessions <= 0) {
            return null;
        }

        return DB::transaction(function () use ($item, $proc, $patientId, $branchId) {
            $expiresAt = $proc->package_validity_days > 0
                ? now()->addDays($proc->package_validity_days)->toDateString()
                : null;

            return Course::create([
                'branch_id' => $branchId,
                'patient_id' => $patientId,
                'name' => $proc->name,
                'total_sessions' => (int) $proc->package_sessions * (int) $item->quantity,
                'used_sessions' => 0,
                'remaining_sessions' => (int) $proc->package_sessions * (int) $item->quantity,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'source_invoice_item_id' => $item->id,
            ]);
        });
    }

    /**
     * Consume one session of a course.
     */
    public function useSession(Course $course, Visit $visit, ?User $doctor = null, ?string $notes = null): CourseUsage
    {
        return DB::transaction(function () use ($course, $visit, $doctor, $notes) {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);

            if ($course->status !== 'active') {
                throw ValidationException::withMessages(['course' => "คอร์สไม่ active (status: {$course->status})"]);
            }
            if ($course->expires_at && $course->expires_at->isPast()) {
                $course->status = 'expired';
                $course->save();

                throw ValidationException::withMessages(['course' => 'คอร์สหมดอายุแล้ว']);
            }
            if ((int) $course->remaining_sessions <= 0) {
                throw ValidationException::withMessages(['course' => 'คอร์สใช้ครบแล้ว']);
            }

            $next = (int) $course->used_sessions + 1;
            $usage = CourseUsage::create([
                'course_id' => $course->id,
                'visit_id' => $visit->id,
                'session_number' => $next,
                'used_at' => now(),
                'doctor_id' => $doctor?->id,
                'notes' => $notes,
            ]);

            $course->used_sessions = $next;
            $course->remaining_sessions = (int) $course->total_sessions - $next;
            if ($course->remaining_sessions <= 0) {
                $course->status = 'completed';
            }
            $course->save();

            return $usage;
        });
    }

    public function cancel(Course $course, string $reason): Course
    {
        return DB::transaction(function () use ($course) {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            if (in_array($course->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages(['course' => "คอร์สสถานะ {$course->status} ยกเลิกไม่ได้"]);
            }
            $course->status = 'cancelled';
            $course->save();

            // Note: refund logic to wallet/payment is left to caller (depends on policy)
            return $course;
        });
    }

    /**
     * Mark courses past expiry as expired.
     *
     * @return int count updated
     */
    public function expireExpired(?int $branchId = null): int
    {
        $q = Course::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString());
        if ($branchId) {
            $q->where('branch_id', $branchId);
        }

        return $q->update(['status' => 'expired']);
    }
}
