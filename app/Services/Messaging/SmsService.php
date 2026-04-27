<?php

namespace App\Services\Messaging;

use App\Models\MessagingLog;
use App\Models\MessagingProvider;
use Illuminate\Support\Facades\Http;

/**
 * Generic SMS adapter.
 * Supports two modes via provider config:
 *  - mode=thai_bulk_sms : Thai Bulk SMS API (POST JSON with sender + msisdn + message)
 *  - mode=twilio : Twilio Messages API
 *  - mode=sandbox : just write log without HTTP call (for dev/test)
 */
class SmsService
{
    public function send(MessagingProvider $provider, string $phone, string $message, ?string $relatedType = null, ?int $relatedId = null): bool
    {
        $log = MessagingLog::create([
            'provider_id' => $provider->id,
            'branch_id' => $provider->branch_id,
            'channel' => 'sms',
            'recipient_address' => $this->normalize($phone),
            'payload' => $message,
            'status' => 'pending',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);

        $config = $provider->configArray();
        $mode = $config['mode'] ?? 'sandbox';

        if ($mode === 'sandbox') {
            $log->status = 'sent';
            $log->sent_at = now();
            $log->response = '[sandbox] no real send';
            $log->save();

            return true;
        }

        if ($mode === 'thai_bulk_sms') {
            return $this->sendViaThaiBulkSms($log, $config, $log->recipient_address, $message);
        }

        if ($mode === 'twilio') {
            return $this->sendViaTwilio($log, $config, $log->recipient_address, $message);
        }

        $log->status = 'failed';
        $log->error = "unknown mode {$mode}";
        $log->save();

        return false;
    }

    private function sendViaThaiBulkSms(MessagingLog $log, array $config, string $phone, string $message): bool
    {
        $url = $config['endpoint'] ?? 'https://api.thaibulksms.com/sms';
        $apiKey = $config['api_key'] ?? null;
        $sender = $config['sender'] ?? 'Debut';

        if (! $apiKey) {
            $log->status = 'failed';
            $log->error = 'no api_key configured';
            $log->save();

            return false;
        }

        $resp = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(10)->post($url, [
            'sender' => $sender,
            'msisdn' => $phone,
            'message' => $message,
        ]);

        return $this->handleResponse($log, $resp);
    }

    private function sendViaTwilio(MessagingLog $log, array $config, string $phone, string $message): bool
    {
        $sid = $config['account_sid'] ?? null;
        $token = $config['auth_token'] ?? null;
        $from = $config['from'] ?? null;

        if (! $sid || ! $token || ! $from) {
            $log->status = 'failed';
            $log->error = 'twilio config missing';
            $log->save();

            return false;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        $resp = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->timeout(10)
            ->post($url, [
                'From' => $from,
                'To' => '+'.ltrim($phone, '+'),
                'Body' => $message,
            ]);

        if ($resp->successful() && ($resp->json('sid') ?? null)) {
            $log->status = 'sent';
            $log->sent_at = now();
            $log->external_id = $resp->json('sid');
            $log->response = substr($resp->body(), 0, 2000);
            $log->save();

            return true;
        }

        $log->status = 'failed';
        $log->error = 'HTTP '.$resp->status();
        $log->response = substr($resp->body(), 0, 2000);
        $log->save();

        return false;
    }

    /**
     * Normalize Thailand mobile to international format (08x → 668x).
     */
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/[^0-9+]/', '', $phone);
        if (str_starts_with($digits, '+')) {
            return $digits;
        }
        if (str_starts_with($digits, '00')) {
            return '+'.substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && strlen($digits) >= 9) {
            return '+66'.substr($digits, 1);
        }

        return $digits;
    }

    private function handleResponse(MessagingLog $log, $resp): bool
    {
        $log->response = substr($resp->body(), 0, 2000);
        if ($resp->successful()) {
            $log->status = 'sent';
            $log->sent_at = now();
            $log->external_id = $resp->json('id') ?? $resp->json('message_id');
            $log->save();

            return true;
        }
        $log->status = 'failed';
        $log->error = 'HTTP '.$resp->status();
        $log->save();

        return false;
    }
}
