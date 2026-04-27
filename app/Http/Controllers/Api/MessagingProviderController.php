<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessagingLog;
use App\Models\MessagingProvider;
use App\Services\Messaging\LineMessagingService;
use App\Services\Messaging\MessagingDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessagingProviderController extends Controller
{
    public function __construct(private LineMessagingService $line) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = MessagingProvider::query()
            ->where('branch_id', $branchId)
            ->orderBy('type')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($p) => $this->present($p)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'type' => ['required', Rule::in(MessagingProvider::TYPES)],
            'name' => ['required', 'string', 'max:100'],
            'config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $data['branch_id'] = $branchId;
        $data['is_active'] ??= true;
        $data['is_default'] ??= false;

        // Ensure only one default per channel per branch
        if ($data['is_default']) {
            MessagingProvider::query()
                ->where('branch_id', $branchId)
                ->where('type', $data['type'])
                ->update(['is_default' => false]);
        }

        $provider = MessagingProvider::create($data);

        return response()->json(['data' => $this->present($provider)], 201);
    }

    public function update(Request $request, MessagingProvider $provider): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($provider->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['is_default'])) {
            MessagingProvider::query()
                ->where('branch_id', $branchId)
                ->where('type', $provider->type)
                ->where('id', '!=', $provider->id)
                ->update(['is_default' => false]);
        }

        $provider->fill($data)->save();

        return response()->json(['data' => $this->present($provider->fresh())]);
    }

    public function destroy(MessagingProvider $provider): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($provider->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $provider->delete();

        return response()->json(null, 204);
    }

    public function test(Request $request, MessagingProvider $provider): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($provider->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }

        if ($provider->type === 'line') {
            $result = $this->line->ping($provider);
        } else {
            $result = ['ok' => true, 'note' => 'no live test for this channel — sends a sample log'];
        }

        $provider->status = $result['ok'] ? 'ok' : 'error';
        $provider->last_check_at = now();
        $provider->last_error = $result['ok'] ? null : substr(json_encode($result['body'] ?? $result['error'] ?? ''), 0, 500);
        $provider->save();

        return response()->json(['data' => array_merge($this->present($provider->fresh()), ['test_result' => $result])]);
    }

    public function logs(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $q = MessagingLog::query()
            ->whereHas('provider', fn ($p) => $p->where('branch_id', $branchId))
            ->orderByDesc('id');

        if ($pid = $request->query('provider_id')) {
            $q->where('provider_id', $pid);
        }
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($ch = $request->query('channel')) {
            $q->where('channel', $ch);
        }
        if ($from = $request->query('date_from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (MessagingLog $l) => [
                'id' => $l->id,
                'provider_id' => $l->provider_id,
                'channel' => $l->channel,
                'recipient_address' => $l->recipient_address,
                'status' => $l->status,
                'external_id' => $l->external_id,
                'related_type' => $l->related_type,
                'related_id' => $l->related_id,
                'sent_at' => optional($l->sent_at)->toIso8601String(),
                'created_at' => optional($l->created_at)->toIso8601String(),
                'error' => $l->error,
                'payload_preview' => $l->payload ? mb_substr($l->payload, 0, 200) : null,
            ]),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function retryLog(Request $request, MessagingLog $log): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $provider = $log->provider;
        if (! $provider || $provider->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        if (! in_array($log->status, ['failed', 'bounced'], true)) {
            return response()->json(['message' => 'cannot retry log status='.$log->status], 422);
        }

        $dispatcher = app(MessagingDispatcher::class);
        $body = (string) $log->payload;
        $ok = $dispatcher->send(
            $branchId, $log->channel, $log->recipient_address,
            '', $body, 'messaging_log_retry', $log->id,
        );

        return response()->json(['data' => ['ok' => $ok]]);
    }

    private function present(MessagingProvider $p): array
    {
        $config = $p->configArray();
        $masked = [];
        foreach ($config as $k => $v) {
            $masked[$k] = is_string($v) && strlen($v) > 6
                ? substr($v, 0, 3).str_repeat('*', max(0, strlen($v) - 6)).substr($v, -3)
                : $v;
        }

        return [
            'id' => $p->id,
            'type' => $p->type,
            'name' => $p->name,
            'config' => $masked,
            'is_active' => $p->is_active,
            'is_default' => $p->is_default,
            'status' => $p->status,
            'last_check_at' => optional($p->last_check_at)->toIso8601String(),
            'last_error' => $p->last_error,
            'webhook_url' => $p->type === 'line'
                ? rtrim(config('app.url'), '/').'/api/v1/webhooks/line/'.$p->id
                : null,
        ];
    }
}
