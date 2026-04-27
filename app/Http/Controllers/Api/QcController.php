<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QcChecklist;
use App\Models\QcChecklistItem;
use App\Models\QcRun;
use App\Services\Qc\QcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QcController extends Controller
{
    public function __construct(private QcService $qc) {}

    // ───── Checklists ─────

    public function checklistsIndex(): JsonResponse
    {
        return response()->json(['data' => QcChecklist::with('items')->orderBy('name')->paginate(50)]);
    }

    public function checklistsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'frequency' => ['required', 'in:daily,weekly,monthly,per_visit'],
            'applicable_role' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.title' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.requires_photo' => ['nullable', 'boolean'],
            'items.*.requires_note' => ['nullable', 'boolean'],
            'items.*.default_pass' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($data) {
            $cl = QcChecklist::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'frequency' => $data['frequency'],
                'applicable_role' => $data['applicable_role'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
            foreach (($data['items'] ?? []) as $idx => $item) {
                QcChecklistItem::create([
                    'checklist_id' => $cl->id,
                    'position' => $idx,
                    'title' => $item['title'],
                    'description' => $item['description'] ?? null,
                    'requires_photo' => $item['requires_photo'] ?? false,
                    'requires_note' => $item['requires_note'] ?? false,
                    'default_pass' => $item['default_pass'] ?? true,
                ]);
            }

            return response()->json(['data' => $cl->load('items')], 201);
        });
    }

    public function checklistsUpdate(Request $request, QcChecklist $checklist): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'frequency' => ['sometimes', 'in:daily,weekly,monthly,per_visit'],
            'applicable_role' => ['nullable', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($data, $checklist) {
            $checklist->fill(collect($data)->except('items')->all())->save();
            if (isset($data['items'])) {
                $checklist->items()->delete();
                foreach ($data['items'] as $idx => $item) {
                    QcChecklistItem::create([
                        'checklist_id' => $checklist->id,
                        'position' => $idx,
                        'title' => $item['title'] ?? '',
                        'description' => $item['description'] ?? null,
                        'requires_photo' => $item['requires_photo'] ?? false,
                        'requires_note' => $item['requires_note'] ?? false,
                        'default_pass' => $item['default_pass'] ?? true,
                    ]);
                }
            }

            return response()->json(['data' => $checklist->fresh('items')]);
        });
    }

    public function checklistsDestroy(QcChecklist $checklist): JsonResponse
    {
        $checklist->delete();

        return response()->json(null, 204);
    }

    // ───── Runs ─────

    public function runsIndex(Request $request): JsonResponse
    {
        $q = QcRun::with(['checklist:id,name,frequency', 'performer:id,name'])
            ->withCount('items');
        if ($request->filled('checklist_id')) {
            $q->where('checklist_id', (int) $request->checklist_id);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $q->whereDate('run_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('run_date', '<=', $request->to);
        }

        return response()->json(['data' => $q->orderByDesc('run_date')->orderByDesc('id')->paginate(50)]);
    }

    public function runShow(QcRun $run): JsonResponse
    {
        $run->load(['checklist.items', 'performer:id,name', 'items.item']);

        return response()->json(['data' => $run]);
    }

    public function runStart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'checklist_id' => ['required', 'integer'],
            'date' => ['nullable', 'date'],
        ]);
        $checklist = QcChecklist::findOrFail($data['checklist_id']);
        $run = $this->qc->startRun($checklist, $request->user(), $data['date'] ?? null);

        return response()->json(['data' => $run->load(['checklist.items', 'items'])], 201);
    }

    public function runRecordItem(Request $request, QcRun $run): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'status' => ['required', 'in:pass,fail,na'],
            'note' => ['nullable', 'string', 'max:500'],
            'photo_path' => ['nullable', 'string', 'max:500'],
        ]);
        $item = QcChecklistItem::where('checklist_id', $run->checklist_id)
            ->findOrFail($data['item_id']);
        $row = $this->qc->recordItem($run, $item, $data['status'], $data['note'] ?? null, $data['photo_path'] ?? null);

        return response()->json(['data' => $row], 201);
    }

    public function runComplete(QcRun $run): JsonResponse
    {
        $run = $this->qc->completeRun($run);

        return response()->json(['data' => $run->load(['items.item', 'checklist'])]);
    }

    public function summary(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->subDays(30)->toDateString());
        $to = $request->query('to', now()->toDateString());

        return response()->json(['data' => array_merge(
            ['from' => $from, 'to' => $to],
            $this->qc->summary($branchId, $from, $to),
        )]);
    }
}
