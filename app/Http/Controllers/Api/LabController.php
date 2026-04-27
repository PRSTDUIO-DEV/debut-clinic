<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Patient;
use App\Services\LabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabController extends Controller
{
    public function __construct(private LabService $labs) {}

    // ───── Lab Test catalog ─────

    public function tests(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = LabTest::query()
            ->where('branch_id', $branchId)
            ->when($request->boolean('only_active', true), fn ($q) => $q->where('is_active', true))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function storeTest(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('lab_tests')->where(fn ($q) => $q->where('branch_id', $branchId))],
            'name' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:60'],
            'unit' => ['nullable', 'string', 'max:30'],
            'ref_min' => ['nullable', 'numeric'],
            'ref_max' => ['nullable', 'numeric'],
            'ref_text' => ['nullable', 'string', 'max:200'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['branch_id'] = $branchId;
        $data['is_active'] ??= true;

        return response()->json(['data' => LabTest::create($data)], 201);
    }

    public function updateTest(Request $request, LabTest $test): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($test->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:60'],
            'unit' => ['nullable', 'string', 'max:30'],
            'ref_min' => ['nullable', 'numeric'],
            'ref_max' => ['nullable', 'numeric'],
            'ref_text' => ['nullable', 'string', 'max:200'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $test->fill($data)->save();

        return response()->json(['data' => $test]);
    }

    public function destroyTest(LabTest $test): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($test->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $test->delete();

        return response()->json(null, 204);
    }

    // ───── Lab Orders ─────

    public function orders(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $q = LabOrder::query()
            ->where('branch_id', $branchId)
            ->with(['patient:id,uuid,hn,first_name,last_name', 'orderer:id,name', 'items'])
            ->orderByDesc('id');

        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($pUuid = $request->query('patient_uuid')) {
            $patient = Patient::query()->where('uuid', $pUuid)->where('branch_id', $branchId)->first();
            if ($patient) {
                $q->where('patient_id', $patient->id);
            } else {
                $q->whereRaw('1 = 0');
            }
        }
        if ($from = $request->query('date_from')) {
            $q->whereDate('ordered_at', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $q->whereDate('ordered_at', '<=', $to);
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (LabOrder $o) => $this->presentOrder($o, withResults: false)),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(LabOrder $order): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($order->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $order->load(['patient:id,uuid,hn,first_name,last_name', 'orderer:id,name', 'items.test', 'results.test']);

        return response()->json(['data' => $this->presentOrder($order, withResults: true)]);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'patient_uuid' => ['required', 'string'],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'test_ids' => ['required', 'array', 'min:1'],
            'test_ids.*' => ['integer', 'exists:lab_tests,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in(['draft', 'sent'])],
        ]);
        $patient = Patient::query()
            ->where('uuid', $data['patient_uuid'])
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $order = $this->labs->createOrder(
            patient: $patient,
            testIds: $data['test_ids'],
            visitId: $data['visit_id'] ?? null,
            user: $request->user(),
            notes: $data['notes'] ?? null,
            status: $data['status'] ?? 'draft',
        );

        return response()->json(['data' => $this->presentOrder($order->fresh(['items.test']), withResults: false)], 201);
    }

    public function recordResults(Request $request, LabOrder $order): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($order->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'result_date' => ['nullable', 'date'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.lab_test_id' => ['required', 'integer'],
            'rows.*.value_numeric' => ['nullable', 'numeric'],
            'rows.*.value_text' => ['nullable', 'string', 'max:200'],
            'rows.*.abnormal_flag' => ['nullable', Rule::in(['normal', 'low', 'high', 'critical'])],
            'rows.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->labs->recordResults($order, $data['rows'], $request->user(), $data['result_date'] ?? null);
        $order->load(['items.test', 'results.test']);

        return response()->json(['data' => $this->presentOrder($order, withResults: true)]);
    }

    public function attachReport(Request $request, LabOrder $order): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($order->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $request->validate(['file' => ['required', 'file', 'max:12288']]);
        $this->labs->attachReport($order, $request->file('file'));

        return response()->json(['data' => ['report_url' => $order->fresh()->reportUrl()]]);
    }

    public function cancelOrder(Request $request, LabOrder $order): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($order->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->labs->cancel($order, $data['reason']);

        return response()->json(['data' => $this->presentOrder($order->fresh(), withResults: false)]);
    }

    public function byPatient(string $patientUuid): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $patient = Patient::query()
            ->where('uuid', $patientUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $rows = LabOrder::query()
            ->where('branch_id', $branchId)
            ->where('patient_id', $patient->id)
            ->with(['items.test', 'results.test', 'orderer:id,name'])
            ->orderByDesc('ordered_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (LabOrder $o) => $this->presentOrder($o, withResults: true, withPatient: false)),
        ]);
    }

    private function presentOrder(LabOrder $o, bool $withResults, bool $withPatient = true): array
    {
        $row = [
            'id' => $o->id,
            'order_no' => $o->order_no,
            'status' => $o->status,
            'ordered_at' => optional($o->ordered_at)->toIso8601String(),
            'result_date' => optional($o->result_date)->toDateString(),
            'ordered_by' => $o->orderer?->name,
            'notes' => $o->notes,
            'report_url' => $o->reportUrl(),
            'item_count' => $o->items?->count() ?? 0,
        ];
        if ($withPatient && $o->relationLoaded('patient')) {
            $row['patient'] = $o->patient ? [
                'uuid' => $o->patient->uuid,
                'hn' => $o->patient->hn,
                'name' => trim(($o->patient->first_name ?? '').' '.($o->patient->last_name ?? '')),
            ] : null;
        }
        if ($withResults) {
            $byTest = $o->results?->keyBy('lab_test_id') ?? collect();
            $row['rows'] = ($o->items ?? collect())->map(function ($it) use ($byTest) {
                $r = $byTest->get($it->lab_test_id);

                return [
                    'lab_test_id' => $it->lab_test_id,
                    'code' => $it->test?->code,
                    'name' => $it->test?->name,
                    'unit' => $it->test?->unit,
                    'ref_min' => $it->test?->ref_min !== null ? (float) $it->test->ref_min : null,
                    'ref_max' => $it->test?->ref_max !== null ? (float) $it->test->ref_max : null,
                    'ref_text' => $it->test?->ref_text,
                    'value_numeric' => $r?->value_numeric !== null ? (float) $r->value_numeric : null,
                    'value_text' => $r?->value_text,
                    'abnormal_flag' => $r?->abnormal_flag,
                    'notes' => $r?->notes,
                    'measured_at' => optional($r?->measured_at)->toIso8601String(),
                ];
            })->values();
        }

        return $row;
    }
}
