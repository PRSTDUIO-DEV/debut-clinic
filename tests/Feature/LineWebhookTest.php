<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MessagingLog;
use App\Models\MessagingProvider;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LineWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(): MessagingProvider
    {
        $branch = Branch::factory()->create();

        return MessagingProvider::create([
            'branch_id' => $branch->id, 'type' => 'line', 'name' => 'Test',
            'config' => ['channel_id' => 'CID', 'channel_secret' => 'secret', 'channel_access_token' => 'token'],
            'is_active' => true,
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $provider = $this->makeProvider();
        $body = json_encode(['events' => []]);

        $this->postJson("/api/v1/webhooks/line/{$provider->id}", json_decode($body, true), [
            'X-Line-Signature' => 'wrong',
        ])->assertStatus(401);
    }

    public function test_webhook_processes_message_event(): void
    {
        Http::fake([
            'api.line.me/*' => Http::response('{}', 200),
        ]);
        $provider = $this->makeProvider();

        $events = ['events' => [[
            'type' => 'message',
            'source' => ['userId' => 'U-known'],
            'message' => ['type' => 'text', 'text' => 'นัดถัดไปคืออะไร'],
        ]]];
        $body = json_encode($events);
        $signature = base64_encode(hash_hmac('sha256', $body, 'secret', true));

        // Pre-link a patient to U-known
        $patient = Patient::factory()->create([
            'branch_id' => $provider->branch_id,
            'first_name' => 'สมชาย',
            'line_user_id' => 'U-known',
        ]);

        $this->call('POST', "/api/v1/webhooks/line/{$provider->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => $signature,
        ], $body)->assertOk();

        // 1 inbound log + 1 outbound reply
        $this->assertSame(2, MessagingLog::query()->where('provider_id', $provider->id)->count());
    }

    public function test_webhook_unfollow_unlinks_patient(): void
    {
        Http::fake();
        $provider = $this->makeProvider();
        $patient = Patient::factory()->create([
            'branch_id' => $provider->branch_id,
            'line_user_id' => 'U-leaving',
            'line_linked_at' => now(),
        ]);

        $events = ['events' => [[
            'type' => 'unfollow',
            'source' => ['userId' => 'U-leaving'],
        ]]];
        $body = json_encode($events);
        $signature = base64_encode(hash_hmac('sha256', $body, 'secret', true));

        $this->call('POST', "/api/v1/webhooks/line/{$provider->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => $signature,
        ], $body)->assertOk();

        $this->assertNull($patient->fresh()->line_user_id);
    }
}
