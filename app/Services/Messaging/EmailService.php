<?php

namespace App\Services\Messaging;

use App\Models\MessagingLog;
use App\Models\MessagingProvider;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    public function send(MessagingProvider $provider, string $to, string $subject, string $body, ?string $relatedType = null, ?int $relatedId = null): bool
    {
        $log = MessagingLog::create([
            'provider_id' => $provider->id,
            'branch_id' => $provider->branch_id,
            'channel' => 'email',
            'recipient_address' => $to,
            'payload' => json_encode(['subject' => $subject, 'body' => substr($body, 0, 500)], JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);

        $config = $provider->configArray();
        $fromAddress = $config['from_address'] ?? config('mail.from.address') ?? 'noreply@debut-clinic.local';
        $fromName = $config['from_name'] ?? config('mail.from.name') ?? 'Debut Clinic';

        try {
            Mail::raw($body, function ($message) use ($to, $subject, $fromAddress, $fromName) {
                $message->to($to)->subject($subject)->from($fromAddress, $fromName);
            });
            $log->status = 'sent';
            $log->sent_at = now();
            $log->save();

            return true;
        } catch (\Throwable $e) {
            $log->status = 'failed';
            $log->error = substr($e->getMessage(), 0, 500);
            $log->save();

            return false;
        }
    }
}
