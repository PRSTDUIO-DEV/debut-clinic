<?php

namespace App\Services\Messaging;

use App\Models\MessagingProvider;

/**
 * Routes outbound messages to the appropriate provider/service.
 * Each branch can have multiple providers per channel; uses default first,
 * else first active.
 */
class MessagingDispatcher
{
    public function __construct(
        private LineMessagingService $line,
        private SmsService $sms,
        private EmailService $email,
    ) {}

    /**
     * Get default active provider for branch+channel.
     */
    public function resolveProvider(int $branchId, string $channel): ?MessagingProvider
    {
        return MessagingProvider::query()
            ->where('branch_id', $branchId)
            ->where('type', $channel)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function send(int $branchId, string $channel, string $address, string $subject, string $body, ?string $relatedType = null, ?int $relatedId = null): bool
    {
        $provider = $this->resolveProvider($branchId, $channel);
        if (! $provider) {
            return false;
        }

        return match ($channel) {
            'line' => $this->line->pushText($provider, $address, $body, $relatedType, $relatedId),
            'sms' => $this->sms->send($provider, $address, $body, $relatedType, $relatedId),
            'email' => $this->email->send($provider, $address, $subject, $body, $relatedType, $relatedId),
            default => false,
        };
    }
}
