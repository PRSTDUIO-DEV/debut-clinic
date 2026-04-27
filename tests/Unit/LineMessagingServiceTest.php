<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\MessagingLog;
use App\Models\MessagingProvider;
use App\Services\Messaging\LineMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LineMessagingServiceTest extends TestCase
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

    public function test_push_text_writes_log_and_marks_sent(): void
    {
        Http::fake([
            'api.line.me/*' => Http::response('{}', 200, ['X-Line-Request-Id' => 'req-123']),
        ]);
        $provider = $this->makeProvider();
        $svc = $this->app->make(LineMessagingService::class);

        $ok = $svc->pushText($provider, 'U-test', 'hello');

        $this->assertTrue($ok);
        $log = MessagingLog::query()->latest('id')->first();
        $this->assertSame('sent', $log->status);
        $this->assertSame('U-test', $log->recipient_address);
        $this->assertSame('req-123', $log->external_id);
    }

    public function test_push_text_marks_failed_on_http_error(): void
    {
        Http::fake([
            'api.line.me/*' => Http::response('{"message":"invalid"}', 400),
        ]);
        $provider = $this->makeProvider();
        $svc = $this->app->make(LineMessagingService::class);

        $ok = $svc->pushText($provider, 'U-test', 'hi');

        $this->assertFalse($ok);
        $this->assertSame('failed', MessagingLog::query()->latest('id')->first()->status);
    }

    public function test_push_text_fails_without_token(): void
    {
        $branch = Branch::factory()->create();
        $provider = MessagingProvider::create([
            'branch_id' => $branch->id, 'type' => 'line', 'name' => 'NoToken',
            'config' => ['channel_id' => 'X', 'channel_secret' => 'Y'],
            'is_active' => true,
        ]);
        $svc = $this->app->make(LineMessagingService::class);

        $this->assertFalse($svc->pushText($provider, 'U-test', 'hi'));
        $log = MessagingLog::query()->latest('id')->first();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('no channel_access_token', $log->error);
    }

    public function test_verify_signature_correct(): void
    {
        $svc = $this->app->make(LineMessagingService::class);
        $body = '{"events":[]}';
        $secret = 's3cr3t';
        $sig = base64_encode(hash_hmac('sha256', $body, $secret, true));

        $this->assertTrue($svc->verifySignature($body, $sig, $secret));
        $this->assertFalse($svc->verifySignature($body, 'badsig', $secret));
    }
}
