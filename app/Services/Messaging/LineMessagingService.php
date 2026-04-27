<?php

namespace App\Services\Messaging;

use App\Models\MessagingLog;
use App\Models\MessagingProvider;
use Illuminate\Support\Facades\Http;

class LineMessagingService
{
    public const API_BASE = 'https://api.line.me/v2/bot';

    /**
     * Push a text message to a LINE userId.
     * Returns true on success.
     */
    public function pushText(MessagingProvider $provider, string $toUserId, string $message, ?string $relatedType = null, ?int $relatedId = null): bool
    {
        $log = MessagingLog::create([
            'provider_id' => $provider->id,
            'branch_id' => $provider->branch_id,
            'channel' => 'line',
            'recipient_address' => $toUserId,
            'payload' => json_encode(['type' => 'text', 'text' => $message], JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);

        $token = $provider->configArray()['channel_access_token'] ?? null;
        if (! $token) {
            $this->failLog($log, 'no channel_access_token configured');

            return false;
        }

        $resp = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(10)
            ->post(self::API_BASE.'/message/push', [
                'to' => $toUserId,
                'messages' => [['type' => 'text', 'text' => $message]],
            ]);

        return $this->handleResponse($log, $resp);
    }

    /**
     * Push a flex message (richer card-based content).
     *
     * @param array<string,mixed> $flexContents
     */
    public function pushFlex(MessagingProvider $provider, string $toUserId, string $altText, array $flexContents, ?string $relatedType = null, ?int $relatedId = null): bool
    {
        $log = MessagingLog::create([
            'provider_id' => $provider->id,
            'branch_id' => $provider->branch_id,
            'channel' => 'line',
            'recipient_address' => $toUserId,
            'payload' => json_encode(['type' => 'flex', 'altText' => $altText, 'contents' => $flexContents], JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);

        $token = $provider->configArray()['channel_access_token'] ?? null;
        if (! $token) {
            $this->failLog($log, 'no channel_access_token configured');

            return false;
        }

        $resp = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(10)
            ->post(self::API_BASE.'/message/push', [
                'to' => $toUserId,
                'messages' => [[
                    'type' => 'flex',
                    'altText' => $altText,
                    'contents' => $flexContents,
                ]],
            ]);

        return $this->handleResponse($log, $resp);
    }

    /**
     * Verify LINE webhook signature header X-Line-Signature.
     */
    public function verifySignature(string $rawBody, string $signature, string $channelSecret): bool
    {
        $expected = base64_encode(hash_hmac('sha256', $rawBody, $channelSecret, true));

        return hash_equals($expected, $signature);
    }

    public function getProfile(MessagingProvider $provider, string $userId): ?array
    {
        $token = $provider->configArray()['channel_access_token'] ?? null;
        if (! $token) {
            return null;
        }
        $resp = Http::withToken($token)
            ->timeout(10)
            ->get(self::API_BASE.'/profile/'.$userId);
        if ($resp->successful()) {
            return $resp->json();
        }

        return null;
    }

    /**
     * Verify if API credentials work — used by /providers/{id}/test.
     */
    public function ping(MessagingProvider $provider): array
    {
        $token = $provider->configArray()['channel_access_token'] ?? null;
        if (! $token) {
            return ['ok' => false, 'error' => 'no channel_access_token'];
        }
        $resp = Http::withToken($token)
            ->timeout(5)
            ->get(self::API_BASE.'/info');

        return [
            'ok' => $resp->successful(),
            'status' => $resp->status(),
            'body' => $resp->json() ?: $resp->body(),
        ];
    }

    private function handleResponse(MessagingLog $log, $resp): bool
    {
        $log->response = is_string($resp->body()) ? substr($resp->body(), 0, 2000) : null;
        if ($resp->successful()) {
            $log->status = 'sent';
            $log->sent_at = now();
            $log->external_id = $resp->header('X-Line-Request-Id');
            $log->save();

            return true;
        }
        $log->status = 'failed';
        $log->error = "HTTP {$resp->status()}";
        $log->save();

        return false;
    }

    private function failLog(MessagingLog $log, string $error): void
    {
        $log->status = 'failed';
        $log->error = $error;
        $log->save();
    }
}
