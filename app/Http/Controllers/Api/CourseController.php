<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(private CourseService $courses) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $q = Course::query()
            ->where('branch_id', $branchId)
            ->with(['patient:id,uuid,hn,first_name,last_name'])
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($s = $request->query('q')) {
            $q->where(function ($qq) use ($s) {
                $qq->where('name', 'like', "%{$s}%")
                    ->orWhereHas('patient', function ($p) use ($s) {
                        $p->where('hn', 'like', "%{$s}%")
                            ->orWhere('first_name', 'like', "%{$s}%")
                            ->orWhere('last_name', 'like', "%{$s}%");
                    });
            });
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (Course $c) => $this->present($c)),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function byPatient(string $patientUuid): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $patient = Patient::query()
            ->where('uuid', $patientUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $rows = Course::query()
            ->where('branch_id', $branchId)
            ->where('patient_id', $patient->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $rows->map(fn (Course $c) => $this->present($c, withPatient: false))]);
    }

    public function show(Course $course): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($course->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $course->load(['patient:id,uuid,hn,first_name,last_name', 'usages.visit:id,uuid,visit_number,check_in_at']);

        return response()->json(['data' => array_merge($this->present($course), [
            'usages' => $course->usages->map(fn ($u) => [
                'session_number' => $u->session_number,
                'used_at' => $u->used_at?->toIso8601String(),
                'visit' => $u->visit ? ['uuid' => $u->visit->uuid, 'visit_number' => $u->visit->visit_number] : null,
                'doctor_id' => $u->doctor_id,
                'notes' => $u->notes,
            ]),
        ])]);
    }

    public function useSession(Request $request, Course $course): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($course->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }

        $data = $request->validate([
            'visit_uuid' => ['required', 'string'],
            'doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $visit = Visit::query()
            ->where('uuid', $data['visit_uuid'])
            ->where('branch_id', $branchId)
            ->firstOrFail();
        $doctor = ! empty($data['doctor_id']) ? User::query()->find($data['doctor_id']) : null;

        $usage = $this->courses->useSession($course, $visit, $doctor, $data['notes'] ?? null);

        return response()->json([
            'data' => [
                'usage' => [
                    'session_number' => $usage->session_number,
                    'used_at' => $usage->used_at?->toIso8601String(),
                ],
                'course' => $this->present($course->fresh()),
            ],
        ], 201);
    }

    public function cancel(Request $request, Course $course): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($course->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->courses->cancel($course, $data['reason']);

        return response()->json(['data' => $this->present($course->fresh())]);
    }

    private function present(Course $c, bool $withPatient = true): array
    {
        $row = [
            'id' => $c->id,
            'name' => $c->name,
            'total_sessions' => (int) $c->total_sessions,
            'used_sessions' => (int) $c->used_sessions,
            'remaining_sessions' => (int) $c->remaining_sessions,
            'expires_at' => $c->expires_at?->toDateString(),
            'status' => $c->status,
            'created_at' => optional($c->created_at)->toIso8601String(),
        ];
        if ($withPatient && $c->relationLoaded('patient')) {
            $row['patient'] = $c->patient ? [
                'uuid' => $c->patient->uuid,
                'hn' => $c->patient->hn,
                'name' => trim(($c->patient->first_name ?? '').' '.($c->patient->last_name ?? '')),
            ] : null;
        }

        return $row;
    }
}
