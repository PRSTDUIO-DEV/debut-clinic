<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastSegment;
use App\Models\BroadcastTemplate;
use App\Services\BroadcastService;
use App\Services\SegmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmController extends Controller
{
    public function __construct(
        private SegmentService $segments,
        private BroadcastService $broadcasts,
    ) {}

    // ───── Segments ─────

    public function segments(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = BroadcastSegment::query()
            ->where('branch_id', $branchId)
            ->when($request->boolean('only_active', false), fn ($q) => $q->where('is_active', true))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($s) => $this->presentSegment($s)),
        ]);
    }

    public function showSegment(BroadcastSegment $segment): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($segment->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }

        return response()->json(['data' => $this->presentSegment($segment)]);
    }

    public function storeSegment(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'rules' => ['present', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['branch_id'] = $branchId;
        $data['created_by'] = $request->user()->id;
        $data['is_active'] ??= true;
        $data['rules'] = $data['rules'] ?? [];

        $segment = BroadcastSegment::create($data);
        $this->segments->touchStats($segment);

        return response()->json(['data' => $this->presentSegment($segment->fresh())], 201);
    }

    public function updateSegment(Request $request, BroadcastSegment $segment): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($segment->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'rules' => ['sometimes', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $segment->fill($data)->save();
        $this->segments->touchStats($segment);

        return response()->json(['data' => $this->presentSegment($segment->fresh())]);
    }

    public function destroySegment(BroadcastSegment $segment): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($segment->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $segment->delete();

        return response()->json(null, 204);
    }

    public function previewSegment(Request $request, BroadcastSegment $segment): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($segment->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $limit = (int) min(50, max(1, $request->integer('limit', 20)));
        $patients = $this->segments->query($segment)->limit($limit)->get();
        $count = $this->segments->count($segment);

        return response()->json([
            'data' => [
                'count' => $count,
                'samples' => $patients->map(fn ($p) => [
                    'uuid' => $p->uuid,
                    'hn' => $p->hn,
                    'name' => trim(($p->first_name ?? '').' '.($p->last_name ?? '')),
                    'phone' => $p->phone,
                    'line_id' => $p->line_id,
                    'email' => $p->email,
                    'last_visit_at' => optional($p->last_visit_at)->toDateString(),
                    'total_spent' => (float) $p->total_spent,
                ]),
            ],
        ]);
    }

    // ───── Templates ─────

    public function templates(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = BroadcastTemplate::query()
            ->where('branch_id', $branchId)
            ->when($channel = $request->query('channel'), fn ($q) => $q->where('channel', $channel))
            ->when($request->boolean('only_active', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('channel')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('broadcast_templates')->where(fn ($q) => $q->where('branch_id', $branchId))],
            'name' => ['required', 'string', 'max:150'],
            'channel' => ['required', Rule::in(BroadcastTemplate::CHANNELS)],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['branch_id'] = $branchId;
        $data['created_by'] = $request->user()->id;
        $data['is_active'] ??= true;

        return response()->json(['data' => BroadcastTemplate::create($data)], 201);
    }

    public function updateTemplate(Request $request, BroadcastTemplate $template): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($template->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['sometimes', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $template->fill($data)->save();

        return response()->json(['data' => $template]);
    }

    public function destroyTemplate(BroadcastTemplate $template): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($template->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $template->delete();

        return response()->json(null, 204);
    }

    // ───── Campaigns ─────

    public function campaigns(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $q = BroadcastCampaign::query()
            ->where('branch_id', $branchId)
            ->with(['segment:id,name', 'template:id,name,channel', 'creator:id,name'])
            ->orderByDesc('id');

        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn ($c) => $this->presentCampaign($c)),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function showCampaign(BroadcastCampaign $campaign): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($campaign->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $campaign->load(['segment', 'template', 'creator:id,name', 'messages.patient:id,uuid,hn,first_name,last_name']);

        return response()->json([
            'data' => array_merge($this->presentCampaign($campaign), [
                'messages' => $campaign->messages->take(100)->map(fn ($m) => [
                    'id' => $m->id,
                    'patient' => $m->patient ? [
                        'uuid' => $m->patient->uuid,
                        'hn' => $m->patient->hn,
                        'name' => trim(($m->patient->first_name ?? '').' '.($m->patient->last_name ?? '')),
                    ] : null,
                    'channel' => $m->channel,
                    'recipient_address' => $m->recipient_address,
                    'status' => $m->status,
                    'error' => $m->error,
                    'sent_at' => optional($m->sent_at)->toIso8601String(),
                ])->values(),
            ]),
        ]);
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'segment_id' => ['required', 'integer', 'exists:broadcast_segments,id'],
            'template_id' => ['required', 'integer', 'exists:broadcast_templates,id'],
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],
        ]);
        $segment = BroadcastSegment::query()->where('id', $data['segment_id'])->where('branch_id', $branchId)->firstOrFail();
        $template = BroadcastTemplate::query()->where('id', $data['template_id'])->where('branch_id', $branchId)->firstOrFail();

        $campaign = $this->broadcasts->createCampaign(
            $segment, $template, $data['name'],
            $data['scheduled_at'] ?? null, $request->user(),
        );

        return response()->json(['data' => $this->presentCampaign($campaign->fresh(['segment', 'template']))], 201);
    }

    public function sendNowCampaign(BroadcastCampaign $campaign): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($campaign->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $this->broadcasts->sendNow($campaign);

        return response()->json(['data' => $this->presentCampaign($campaign->fresh(['segment', 'template']))]);
    }

    public function cancelCampaign(Request $request, BroadcastCampaign $campaign): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($campaign->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->broadcasts->cancel($campaign, $data['reason']);

        return response()->json(['data' => $this->presentCampaign($campaign->fresh())]);
    }

    private function presentSegment(BroadcastSegment $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'description' => $s->description,
            'rules' => $s->rules,
            'last_resolved_count' => $s->last_resolved_count,
            'last_resolved_at' => optional($s->last_resolved_at)->toIso8601String(),
            'is_active' => $s->is_active,
            'created_at' => optional($s->created_at)->toIso8601String(),
        ];
    }

    private function presentCampaign(BroadcastCampaign $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'status' => $c->status,
            'segment' => $c->segment ? ['id' => $c->segment->id, 'name' => $c->segment->name] : null,
            'template' => $c->template ? ['id' => $c->template->id, 'name' => $c->template->name, 'channel' => $c->template->channel] : null,
            'scheduled_at' => optional($c->scheduled_at)->toIso8601String(),
            'started_at' => optional($c->started_at)->toIso8601String(),
            'completed_at' => optional($c->completed_at)->toIso8601String(),
            'total_recipients' => $c->total_recipients,
            'sent_count' => $c->sent_count,
            'failed_count' => $c->failed_count,
            'skipped_count' => $c->skipped_count,
            'created_by' => $c->creator?->name,
        ];
    }
}
