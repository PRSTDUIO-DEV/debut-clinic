<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessagingLog;
use App\Models\MessagingProvider;
use App\Models\Patient;
use App\Models\Scopes\BranchScope;
use App\Services\Messaging\LineMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineWebhookController extends Controller
{
    public function __construct(private LineMessagingService $line) {}

    public function handle(Request $request, int $providerId): JsonResponse
    {
        // Bypass BranchScope — webhook is public + needs to resolve regardless of branch context
        $provider = MessagingProvider::query()
            ->withoutGlobalScope(BranchScope::class)
            ->find($providerId);

        if (! $provider || $provider->type !== 'line' || ! $provider->is_active) {
            return response()->json(['ok' => false, 'error' => 'invalid provider'], 404);
        }

        $signature = (string) $request->header('X-Line-Signature', '');
        $rawBody = $request->getContent();
        $config = $provider->configArray();
        $secret = $config['channel_secret'] ?? null;

        if (! $secret || ! $this->line->verifySignature($rawBody, $signature, $secret)) {
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $events = $request->input('events', []);
        foreach ($events as $event) {
            $this->processEvent($provider, $event);
        }

        return response()->json(['ok' => true, 'count' => count($events)]);
    }

    private function processEvent(MessagingProvider $provider, array $event): void
    {
        $type = $event['type'] ?? null;
        $userId = $event['source']['userId'] ?? null;
        if (! $userId) {
            return;
        }

        // Log inbound for audit
        MessagingLog::create([
            'provider_id' => $provider->id,
            'branch_id' => $provider->branch_id,
            'channel' => 'line',
            'recipient_address' => $userId,
            'payload' => json_encode(['inbound' => true, 'event' => $event], JSON_UNESCAPED_UNICODE),
            'status' => 'sent', // inbound — already received
            'sent_at' => now(),
            'related_type' => 'webhook',
        ]);

        match ($type) {
            'follow' => $this->onFollow($provider, $userId),
            'unfollow' => $this->onUnfollow($provider, $userId),
            'message' => $this->onMessage($provider, $userId, $event['message'] ?? []),
            default => null,
        };
    }

    private function onFollow(MessagingProvider $provider, string $userId): void
    {
        // Send greeting — instruct user to link via LIFF
        $appUrl = rtrim(config('app.url'), '/');
        $greeting = "ขอบคุณที่เพิ่ม Debut Clinic เป็นเพื่อน 💎\n".
            "กรุณาเชื่อม LINE กับบัญชีผู้ป่วยของคุณ:\n{$appUrl}/liff/link-patient";
        $this->line->pushText($provider, $userId, $greeting, 'webhook_follow');
    }

    private function onUnfollow(MessagingProvider $provider, string $userId): void
    {
        // Mark patient unlinked but keep history
        Patient::query()
            ->where('line_user_id', $userId)
            ->update(['line_user_id' => null, 'line_linked_at' => null]);
        Log::info("[line] user {$userId} unfollowed; patient unlinked");
    }

    private function onMessage(MessagingProvider $provider, string $userId, array $message): void
    {
        $text = $message['text'] ?? '';
        if (! $text) {
            return;
        }

        // Find linked patient
        $patient = Patient::query()->where('line_user_id', $userId)->first();
        if (! $patient) {
            $reply = "ระบบยังไม่พบบัญชีผู้ป่วยของคุณ\n".
                'กรุณาลงทะเบียนเชื่อม LINE ที่ '.rtrim(config('app.url'), '/').'/liff/link-patient';
            $this->line->pushText($provider, $userId, $reply, 'webhook_message');

            return;
        }

        // Simple keyword router
        $lower = trim(mb_strtolower($text));
        if (str_contains($lower, 'นัด') || str_contains($lower, 'appointment')) {
            $reply = "คุณ {$patient->first_name} กรุณาคลิกที่นี่เพื่อดูนัดของคุณ:\n".rtrim(config('app.url'), '/').'/patients/'.$patient->uuid;
        } elseif (str_contains($lower, 'wallet') || str_contains($lower, 'ยอด')) {
            $bal = $patient->memberAccount?->balance ?? 0;
            $reply = "คุณ {$patient->first_name} ยอด wallet คงเหลือ ฿".number_format((float) $bal, 2);
        } else {
            $reply = "ขอบคุณ คุณ {$patient->first_name} ทีมงานจะตอบกลับโดยเร็ว";
        }

        $this->line->pushText($provider, $userId, $reply, 'webhook_message');
    }
}
