<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MessagingProvider;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiffLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_patient_with_dev_token_when_no_provider(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create([
            'branch_id' => $branch->id,
            'hn' => 'DC01-260101-9999',
            'phone' => '0811112222',
        ]);

        $res = $this->postJson('/api/v1/liff/link-patient', [
            'id_token' => 'dev:U-test-001:Test User',
            'hn' => 'DC01-260101-9999',
        ])->assertOk();

        $this->assertTrue($res->json('ok'));
        $this->assertSame($patient->uuid, $res->json('data.patient_uuid'));
        $patient->refresh();
        $this->assertSame('U-test-001', $patient->line_user_id);
        $this->assertNotNull($patient->line_linked_at);
    }

    public function test_link_patient_returns_404_when_not_found(): void
    {
        $branch = Branch::factory()->create();

        $this->postJson('/api/v1/liff/link-patient', [
            'id_token' => 'dev:U-orphan:Anon',
            'hn' => 'NON-EXISTENT-HN',
        ])->assertStatus(404);
    }

    public function test_me_endpoint_returns_linked_patient_info(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create([
            'branch_id' => $branch->id,
            'line_user_id' => 'U-linked',
        ]);

        $res = $this->postJson('/api/v1/liff/me', [
            'id_token' => 'dev:U-linked:Friend',
        ])->assertOk();

        $this->assertSame($patient->uuid, $res->json('data.patient.uuid'));
    }

    public function test_link_with_real_provider_verifies_via_oauth(): void
    {
        Http::fake([
            'api.line.me/oauth2/v2.1/verify' => Http::response(['sub' => 'U-real', 'name' => 'Real'], 200),
            'api.line.me/v2/bot/*' => Http::response('{}', 200),
        ]);
        $branch = Branch::factory()->create();
        MessagingProvider::create([
            'branch_id' => $branch->id, 'type' => 'line', 'name' => 'P',
            'config' => ['channel_id' => 'CID', 'channel_secret' => 'sec', 'channel_access_token' => 'tok'],
            'is_active' => true,
        ]);
        $patient = Patient::factory()->create(['branch_id' => $branch->id, 'phone' => '0822223333']);

        $res = $this->postJson('/api/v1/liff/link-patient', [
            'id_token' => 'real-token',
            'phone' => '0822223333',
        ])->assertOk();

        $this->assertSame('U-real', $patient->fresh()->line_user_id);
    }
}
