<?php

namespace App\Services;

use App\Models\BroadcastCampaign;
use App\Models\BroadcastMessage;
use App\Models\BroadcastSegment;
use App\Models\BroadcastTemplate;
use App\Models\Patient;
use App\Models\User;
use App\Services\Messaging\MessagingDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BroadcastService
{
    public function __construct(private SegmentService $segments) {}

    public function createCampaign(
        BroadcastSegment $segment,
        BroadcastTemplate $template,
        string $name,
        ?string $scheduledAt = null,
        ?User $user = null,
    ): BroadcastCampaign {
        if ($segment->branch_id !== $template->branch_id) {
            throw ValidationException::withMessages(['template' => 'segment + template ต้องอยู่สาขาเดียวกัน']);
        }

        return BroadcastCampaign::create([
            'branch_id' => $segment->branch_id,
            'segment_id' => $segment->id,
            'template_id' => $template->id,
            'name' => $name,
            'scheduled_at' => $scheduledAt,
            'status' => $scheduledAt ? 'scheduled' : 'draft',
            'created_by' => $user?->id,
        ]);
    }

    /**
     * Send a campaign immediately. Resolves segment, materializes messages,
     * and pushes to a (simulated) provider per channel.
     */
    public function sendNow(BroadcastCampaign $campaign, ?User $user = null): BroadcastCampaign
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw ValidationException::withMessages(['campaign' => "ส่งไม่ได้ (status: {$campaign->status})"]);
        }

        return DB::transaction(function () use ($campaign) {
            $campaign->status = 'sending';
            $campaign->started_at = now();
            $campaign->save();

            $segment = $campaign->segment;
            $template = $campaign->template;

            $patients = $this->segments->resolve($segment);
            $sent = 0;
            $failed = 0;
            $skipped = 0;

            foreach ($patients as $p) {
                /** @var Patient $p */
                $address = $this->resolveAddress($p, $template->channel);
                $rendered = $this->render($template->body, $p);

                if (! $address) {
                    BroadcastMessage::create([
                        'campaign_id' => $campaign->id,
                        'patient_id' => $p->id,
                        'channel' => $template->channel,
                        'recipient_address' => null,
                        'status' => 'skipped',
                        'payload' => $rendered,
                        'error' => 'no recipient address for channel '.$template->channel,
                    ]);
                    $skipped++;

                    continue;
                }

                $ok = $this->dispatchToProvider($template->channel, $address, $rendered, $template->subject, $campaign->branch_id);

                BroadcastMessage::create([
                    'campaign_id' => $campaign->id,
                    'patient_id' => $p->id,
                    'channel' => $template->channel,
                    'recipient_address' => $address,
                    'status' => $ok ? 'sent' : 'failed',
                    'payload' => $rendered,
                    'error' => $ok ? null : 'simulated provider failure',
                    'sent_at' => $ok ? now() : null,
                ]);
                $ok ? $sent++ : $failed++;
            }

            $campaign->total_recipients = $patients->count();
            $campaign->sent_count = $sent;
            $campaign->failed_count = $failed;
            $campaign->skipped_count = $skipped;
            $campaign->status = $failed > 0 && $sent === 0 ? 'failed' : 'completed';
            $campaign->completed_at = now();
            $campaign->save();

            $this->segments->touchStats($segment);

            return $campaign;
        });
    }

    public function cancel(BroadcastCampaign $campaign, string $reason): BroadcastCampaign
    {
        if (in_array($campaign->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['campaign' => "ยกเลิกไม่ได้ (status: {$campaign->status})"]);
        }
        $campaign->status = 'cancelled';
        $campaign->save();
        Log::info("broadcast campaign {$campaign->id} cancelled: {$reason}");

        return $campaign;
    }

    /**
     * Render template body with simple {{ key }} placeholders.
     */
    public function render(string $body, Patient $patient): string
    {
        $vars = [
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'nickname' => $patient->nickname ?: ($patient->first_name ?? ''),
            'hn' => $patient->hn,
            'phone' => $patient->phone,
            'line_id' => $patient->line_id ?? '',
            'email' => $patient->email ?? '',
            'total_spent' => number_format((float) $patient->total_spent, 0),
        ];

        return preg_replace_callback('/\{\{\s*([\w_]+)\s*\}\}/', function ($m) use ($vars) {
            return $vars[$m[1]] ?? $m[0];
        }, $body);
    }

    private function resolveAddress(Patient $patient, string $channel): ?string
    {
        return match ($channel) {
            // For LINE we need the actual user_id (from LIFF link), not the display handle
            'line' => $patient->line_user_id ?: null,
            'sms' => $patient->phone ?: null,
            'email' => $patient->email ?: null,
            default => null,
        };
    }

    /**
     * Dispatch via real provider when MESSAGING_LIVE=true, else log-only simulated.
     */
    private function dispatchToProvider(string $channel, string $address, string $body, ?string $subject, ?int $branchId = null): bool
    {
        if (config('services.messaging.live') && $branchId) {
            $dispatcher = app(MessagingDispatcher::class);

            return $dispatcher->send($branchId, $channel, $address, $subject ?? '', $body, 'broadcast', null);
        }

        Log::channel('single')->info("[broadcast:{$channel}] to={$address} body=".substr($body, 0, 80));

        return true;
    }
}
