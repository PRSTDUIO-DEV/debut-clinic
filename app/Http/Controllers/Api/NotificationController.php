<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $q = Notification::query()
            ->forUser($userId)
            ->orderByDesc('id');

        if ($s = $request->query('status')) {
            if ($s === 'unread') {
                $q->unread();
            } else {
                $q->where('status', $s);
            }
        }
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }
        if ($from = $request->query('date_from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (Notification $n) => $this->present($n)),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'unread_count' => $this->notifications->unreadCount($userId),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['count' => $this->notifications->unreadCount($request->user()->id)],
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->recipient_type !== 'user' || $notification->recipient_id !== $request->user()->id) {
            return response()->json(['message' => 'forbidden'], 403);
        }
        $this->notifications->markRead($notification);

        return response()->json(['data' => $this->present($notification->fresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notifications->markAllRead($request->user()->id);

        return response()->json(['data' => ['marked' => $count]]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $rows = NotificationPreference::query()
            ->where('user_id', $userId)
            ->get()
            ->keyBy('channel');

        $defaults = [];
        foreach (Notification::CHANNELS as $ch) {
            $row = $rows->get($ch);
            $defaults[] = [
                'channel' => $ch,
                'enabled' => $row?->enabled ?? true,
                'quiet_hours_start' => $row?->quiet_hours_start,
                'quiet_hours_end' => $row?->quiet_hours_end,
            ];
        }

        return response()->json(['data' => $defaults]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.channel' => ['required', Rule::in(Notification::CHANNELS)],
            'preferences.*.enabled' => ['required', 'boolean'],
            'preferences.*.quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'preferences.*.quiet_hours_end' => ['nullable', 'date_format:H:i'],
        ]);
        foreach ($data['preferences'] as $row) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $userId, 'channel' => $row['channel']],
                [
                    'enabled' => $row['enabled'],
                    'quiet_hours_start' => $row['quiet_hours_start'] ?? null,
                    'quiet_hours_end' => $row['quiet_hours_end'] ?? null,
                ],
            );
        }

        return $this->preferences($request);
    }

    private function present(Notification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'severity' => $n->severity,
            'title' => $n->title,
            'body' => $n->body,
            'channel' => $n->channel,
            'status' => $n->status,
            'related_type' => $n->related_type,
            'related_id' => $n->related_id,
            'data' => $n->data,
            'read_at' => optional($n->read_at)->toIso8601String(),
            'sent_at' => optional($n->sent_at)->toIso8601String(),
            'created_at' => optional($n->created_at)->toIso8601String(),
        ];
    }
}
