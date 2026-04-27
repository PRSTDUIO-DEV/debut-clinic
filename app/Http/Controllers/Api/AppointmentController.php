<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Api\Appointment\UpdateStatusRequest;
use App\Http\Resources\Api\AppointmentResource;
use App\Models\Appointment;
use App\Models\FollowUp;
use App\Models\Patient;
use App\Services\ConflictDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function __construct(private ConflictDetector $conflicts) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $query = Appointment::query()->with(['patient', 'doctor', 'room', 'procedure']);

        if ($from = $request->query('date_from')) {
            $query->where('appointment_date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->where('appointment_date', '<=', $to);
        }
        if ($doctor = $request->query('filter.doctor_id')) {
            $query->where('doctor_id', $doctor);
        }
        if ($room = $request->query('filter.room_id')) {
            $query->where('room_id', $room);
        }
        if ($status = $request->query('filter.status')) {
            $query->where('status', $status);
        }

        $query->orderBy('appointment_date')->orderBy('start_time');

        return AppointmentResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $branchId = (int) app('branch.id');

        $patient = Patient::query()->where('uuid', $data['patient_uuid'])->firstOrFail();

        $conflicts = $this->conflicts->findConflicts(
            branchId: $branchId,
            doctorId: (int) $data['doctor_id'],
            roomId: isset($data['room_id']) ? (int) $data['room_id'] : null,
            date: $data['appointment_date'],
            startTime: $data['start_time'].':00',
            endTime: $data['end_time'].':00',
        );
        if (! empty($conflicts)) {
            return response()->json([
                'message' => 'แพทย์มีนัดหมายในช่วงเวลานี้แล้ว',
                'code' => 'conflict',
                'conflicts' => $conflicts,
            ], 409);
        }

        $appointment = Appointment::create([
            'branch_id' => $branchId,
            'patient_id' => $patient->id,
            'doctor_id' => (int) $data['doctor_id'],
            'room_id' => $data['room_id'] ?? null,
            'procedure_id' => $data['procedure_id'] ?? null,
            'appointment_date' => $data['appointment_date'],
            'start_time' => $data['start_time'].':00',
            'end_time' => $data['end_time'].':00',
            'source' => $data['source'] ?? 'manual',
            'follow_up_id' => $data['follow_up_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        $appointment->load(['patient', 'doctor', 'room', 'procedure']);

        return response()->json(['data' => new AppointmentResource($appointment)], 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load(['patient', 'doctor', 'room', 'procedure']);

        return response()->json(['data' => new AppointmentResource($appointment)]);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(UpdateStatusRequest $request, Appointment $appointment): JsonResponse
    {
        $next = $request->validated()['status'];
        if (! $appointment->canTransitionTo($next)) {
            return response()->json([
                'message' => "ไม่สามารถเปลี่ยนสถานะจาก {$appointment->status} เป็น {$next}",
                'code' => 'invalid_transition',
            ], 422);
        }

        $appointment->status = $next;
        $appointment->save();

        $appointment->load(['patient', 'doctor', 'room', 'procedure']);

        return response()->json(['data' => new AppointmentResource($appointment)]);
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'follow_up_id' => ['required', 'integer', 'exists:follow_ups,id'],
            'appointment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('branch_id', app('branch.id'))],
            'notes' => ['nullable', 'string'],
        ]);

        $branchId = (int) app('branch.id');

        return DB::transaction(function () use ($data, $branchId, $request) {
            /** @var FollowUp $follow */
            $follow = FollowUp::query()
                ->where('branch_id', $branchId)
                ->where('id', $data['follow_up_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $follow->doctor_id) {
                return response()->json([
                    'message' => 'Follow-up ไม่มีแพทย์ผูกอยู่',
                    'code' => 'missing_doctor',
                ], 422);
            }

            $conflicts = $this->conflicts->findConflicts(
                branchId: $branchId,
                doctorId: (int) $follow->doctor_id,
                roomId: isset($data['room_id']) ? (int) $data['room_id'] : null,
                date: $data['appointment_date'],
                startTime: $data['start_time'].':00',
                endTime: $data['end_time'].':00',
            );
            if (! empty($conflicts)) {
                return response()->json([
                    'message' => 'แพทย์มีนัดหมายในช่วงเวลานี้แล้ว',
                    'code' => 'conflict',
                    'conflicts' => $conflicts,
                ], 409);
            }

            $appointment = Appointment::create([
                'branch_id' => $branchId,
                'patient_id' => $follow->patient_id,
                'doctor_id' => $follow->doctor_id,
                'room_id' => $data['room_id'] ?? null,
                'procedure_id' => $follow->procedure_id,
                'appointment_date' => $data['appointment_date'],
                'start_time' => $data['start_time'].':00',
                'end_time' => $data['end_time'].':00',
                'source' => 'follow_up',
                'follow_up_id' => $follow->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
                'status' => 'pending',
            ]);

            // Move follow-up to "scheduled"
            if ($follow->canTransitionTo('scheduled')) {
                $follow->status = 'scheduled';
                $follow->save();
            }

            $appointment->load(['patient', 'doctor', 'room', 'procedure']);

            return response()->json([
                'data' => [
                    'appointment' => new AppointmentResource($appointment),
                    'follow_up' => [
                        'id' => $follow->id,
                        'status' => $follow->status,
                    ],
                ],
            ], 201);
        });
    }

    public function availableSlots(Request $request): JsonResponse
    {
        $request->validate([
            'doctor_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'slot_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
        ]);

        $slots = $this->conflicts->availableSlots(
            (int) app('branch.id'),
            (int) $request->query('doctor_id'),
            (string) $request->query('date'),
            (int) $request->query('slot_minutes', 30),
        );

        return response()->json(['data' => $slots]);
    }
}
