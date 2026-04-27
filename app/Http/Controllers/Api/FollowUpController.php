<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FollowUpResource;
use App\Models\FollowUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FollowUpController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $query = FollowUp::query()->with(['patient', 'doctor:id,uuid,name', 'procedure:id,code,name']);

        if ($p = $request->query('filter.priority')) {
            $query->where('priority', $p);
        }
        if ($s = $request->query('filter.status')) {
            $query->where('status', $s);
        }
        if ($d = $request->query('filter.doctor_id')) {
            $query->where('doctor_id', $d);
        }
        if ($from = $request->query('date_from')) {
            $query->whereDate('follow_up_date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->whereDate('follow_up_date', '<=', $to);
        }

        // Custom priority ordering portable across MySQL/SQLite via CASE.
        $driver = $query->getModel()->getConnection()->getDriverName();
        if ($driver === 'mysql') {
            $query->orderByRaw("FIELD(priority, 'critical', 'high', 'normal', 'low')");
        } else {
            $query->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END");
        }
        $query->orderBy('follow_up_date');

        return FollowUpResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(FollowUp $followUp): JsonResponse
    {
        $followUp->load(['patient', 'doctor:id,uuid,name', 'procedure:id,code,name']);

        return response()->json(['data' => new FollowUpResource($followUp)]);
    }

    public function destroy(FollowUp $followUp): JsonResponse
    {
        $followUp->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, FollowUp $followUp): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(FollowUp::STATUSES)],
        ]);

        if (! $followUp->canTransitionTo($data['status'])) {
            return response()->json([
                'message' => "ไม่สามารถเปลี่ยนสถานะจาก {$followUp->status} เป็น {$data['status']}",
                'code' => 'invalid_transition',
            ], 422);
        }

        $followUp->status = $data['status'];
        $followUp->save();

        $followUp->load(['patient', 'doctor:id,uuid,name', 'procedure:id,code,name']);

        return response()->json(['data' => new FollowUpResource($followUp)]);
    }

    public function recordContact(Request $request, FollowUp $followUp): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
            'mark_status' => ['nullable', Rule::in(['contacted', 'scheduled', 'cancelled'])],
        ]);

        $followUp->contact_attempts = (int) $followUp->contact_attempts + 1;
        $followUp->last_contacted_at = now();
        if (isset($data['notes'])) {
            $followUp->notes = trim((string) ($followUp->notes ?? '').
                "\n[".now()->toDateTimeString().'] '.$data['notes']);
        }
        if (! empty($data['mark_status']) && $followUp->canTransitionTo($data['mark_status'])) {
            $followUp->status = $data['mark_status'];
        }
        $followUp->save();

        $followUp->load(['patient', 'doctor:id,uuid,name']);

        return response()->json(['data' => new FollowUpResource($followUp)]);
    }

    public function stats(): JsonResponse
    {
        $branchId = (int) app('branch.id');

        $rows = DB::table('follow_ups')
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $base = ['critical' => 0, 'high' => 0, 'normal' => 0, 'low' => 0];
        foreach ($rows as $k => $v) {
            $base[$k] = (int) $v;
        }
        $base['total'] = array_sum(array_intersect_key($base, array_flip(['critical', 'high', 'normal', 'low'])));

        return response()->json(['data' => $base]);
    }
}
