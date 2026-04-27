<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\MessagingLog;
use App\Models\MessagingProvider;
use App\Services\Messaging\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_thai_mobile(): void
    {
        $svc = $this->app->make(SmsService::class);
        $this->assertSame('+66811112222', $svc->normalize('081-111-2222'));
        $this->assertSame('+66811112222', $svc->normalize('0811112222'));
        $this->assertSame('+1234567890', $svc->normalize('+1234567890'));
        $this->assertSame('+1234567890', $svc->normalize('001234567890'));
    }

    public function test_sandbox_mode_marks_sent_without_http(): void
    {
        $branch = Branch::factory()->create();
        $provider = MessagingProvider::create([
            'branch_id' => $branch->id, 'type' => 'sms', 'name' => 'Sandbox',
            'config' => ['mode' => 'sandbox'],
            'is_active' => true,
        ]);

        $ok = $this->app->make(SmsService::class)->send($provider, '0811112222', 'hi');
        $this->assertTrue($ok);
        $log = MessagingLog::query()->latest('id')->first();
        $this->assertSame('sent', $log->status);
        $this->assertSame('+66811112222', $log->recipient_address);
    }

    public function test_twilio_mode_via_mocked_http(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM-abc'], 201),
        ]);
        $branch = Branch::factory()->create();
        $provider = MessagingProvider::create([
            'branch_id' => $branch->id, 'type' => 'sms', 'name' => 'Twilio',
            'config' => ['mode' => 'twilio', 'account_sid' => 'AC1', 'auth_token' => 'tok', 'from' => '+12345'],
            'is_active' => true,
        ]);

        $ok = $this->app->make(SmsService::class)->send($provider, '0811112222', 'test');
        $this->assertTrue($ok);
        $log = MessagingLog::query()->latest('id')->first();
        $this->assertSame('sent', $log->status);
        $this->assertSame('SM-abc', $log->external_id);
    }

    public function test_unknown_mode_marks_failed(): void
    {
        $branch = Branch::factory()->create();
        $provider = MessagingProvider::create([
            'branch_id' => $branch->id, 'type' => 'sms', 'name' => 'X',
            'config' => ['mode' => 'mystery'],
            'is_active' => true,
        ]);
        $ok = $this->app->make(SmsService::class)->send($provider, '0811112222', 'x');
        $this->assertFalse($ok);
        $this->assertStringContainsString('unknown mode', MessagingLog::query()->latest('id')->first()->error);
    }
}
