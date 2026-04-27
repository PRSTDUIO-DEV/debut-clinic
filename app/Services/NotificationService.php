<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\Messaging\MessagingDispatcher;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Write a notification record (does not send yet).
     *
     * @param array<string,mixed> $data
     */
    public function write(
        string $recipientType,
        int $recipientId,
        string $type,
        string $title,
        ?string $body = null,
        string $severity = 'info',
        string $channel = 'in_app',
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?int $branchId = null,
        ?array $data = null,
    ): Notification {
        return Notification::create([
            'branch_id' => $branchId,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'channel' => $channel,
            'status' => 'pending',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'data' => $data,
        ]);
    }

    /**
     * Convenience: write to all users with a role in a branch.
     *
     * @return array<int, Notification>
     */
    public function writeToRole(
        string $roleName,
        int $branchId,
        string $type,
        string $title,
        ?string $body = null,
        string $severity = 'info',
        string $channel = 'in_app',
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?array $data = null,
    ): array {
        $role = Role::query()->where('name', $roleName)->first();
        if (! $role) {
            return [];
        }

        $userIds = User::query()
            ->where('branch_id', $branchId)
            ->whereHas('roles', fn ($q) => $q->where('roles.id', $role->id))
            ->pluck('id')
            ->all();

        $rows = [];
        foreach ($userIds as $uid) {
            $rows[] = $this->write(
                'user', $uid, $type, $title, $body, $severity, $channel,
                $relatedType, $relatedId, $branchId, $data,
            );
        }

        return $rows;
    }

    /**
     * Dispatch a pending notification via preferred channel.
     */
    public function dispatch(Notification $notification): bool
    {
        if ($notification->status !== 'pending') {
            return false;
        }

        $channel = $this->resolveChannel($notification);
        $ok = match ($channel) {
            'in_app' => true,
            'line' => $this->sendLine($notification),
            'sms' => $this->sendSms($notification),
            'email' => $this->sendEmail($notification),
            default => true,
        };

        $notification->channel = $channel;
        $notification->status = $ok ? 'sent' : 'failed';
        $notification->sent_at = now();
        if (! $ok) {
            $notification->error = 'simulated dispatch failure';
        }
        $notification->save();

        return $ok;
    }

    public function dispatchPending(int $limit = 200): int
    {
        $pending = Notification::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $count = 0;
        foreach ($pending as $n) {
            if ($this->dispatch($n)) {
                $count++;
            }
        }

        return $count;
    }

    public function markRead(Notification $notification): Notification
    {
        if (! $notification->read_at) {
            $notification->read_at = now();
            $notification->status = 'read';
            $notification->save();
        }

        return $notification;
    }

    public function markAllRead(int $userId): int
    {
        return Notification::query()
            ->where('recipient_type', 'user')
            ->where('recipient_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'status' => 'read']);
    }

    public function unreadCount(int $userId): int
    {
        return Notification::query()
            ->forUser($userId)
            ->unread()
            ->count();
    }

    private function resolveChannel(Notification $notification): string
    {
        // Honor user preferences if recipient is a user
        if ($notification->recipient_type === 'user') {
            $pref = NotificationPreference::query()
                ->where('user_id', $notification->recipient_id)
                ->where('channel', $notification->channel)
                ->first();
            if ($pref && ! $pref->enabled) {
                return 'in_app'; // fallback to in_app if user disabled this channel
            }
        }

        return $notification->channel;
    }

    private function sendLine(Notification $n): bool
    {
        return $this->dispatchExternal($n, 'line');
    }

    private function sendSms(Notification $n): bool
    {
        return $this->dispatchExternal($n, 'sms');
    }

    private function sendEmail(Notification $n): bool
    {
        return $this->dispatchExternal($n, 'email');
    }

    private function dispatchExternal(Notification $n, string $channel): bool
    {
        $address = $this->lookupRecipientAddress($n, $channel);
        if (! $address) {
            Log::info("[notify:{$channel}] no address for notification #{$n->id}");

            return false;
        }
        if (config('services.messaging.live') && $n->branch_id) {
            $dispatcher = app(MessagingDispatcher::class);
            $body = $n->body ?? '';
            $subject = $n->title;

            return $dispatcher->send((int) $n->branch_id, $channel, $address, $subject, $body, 'notification', $n->id);
        }
        Log::info("[notify:{$channel}] to=$address title={$n->title}");

        return true;
    }

    private function lookupRecipientAddress(Notification $n, string $channel): ?string
    {
        if ($n->recipient_type === 'patient') {
            $p = Patient::query()->find($n->recipient_id);

            return match ($channel) {
                'line' => $p?->line_user_id ?: ($p?->line_id ?: null),
                'sms' => $p?->phone,
                'email' => $p?->email,
                default => null,
            };
        }
        if ($n->recipient_type === 'user') {
            $u = User::query()->find($n->recipient_id);

            return $u?->email;
        }

        return null;
    }
}
