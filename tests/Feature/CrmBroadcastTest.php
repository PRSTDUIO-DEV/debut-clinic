<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastSegment;
use App\Models\BroadcastTemplate;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\BroadcastService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function bootAdmin(): array
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->branches()->attach($branch->id, ['is_primary' => true]);
        $admin->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($admin);

        return [$branch, $admin];
    }

    public function test_render_substitutes_placeholders(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create([
            'branch_id' => $branch->id,
            'first_name' => 'สมชาย', 'last_name' => 'ใจดี', 'phone' => '0811112222',
        ]);
        $svc = $this->app->make(BroadcastService::class);
        $out = $svc->render('สวัสดีคุณ {{first_name}} ({{phone}})', $patient);
        $this->assertSame('สวัสดีคุณ สมชาย (0811112222)', $out);
    }

    public function test_send_now_distributes_per_channel_and_skips_missing_address(): void
    {
        [$branch] = $this->bootAdmin();
        Patient::factory()->create(['branch_id' => $branch->id, 'phone' => '0811111111']);
        Patient::factory()->create(['branch_id' => $branch->id, 'phone' => null]);
        Patient::factory()->create(['branch_id' => $branch->id, 'phone' => '0822222222']);

        $segment = BroadcastSegment::create([
            'branch_id' => $branch->id, 'name' => 'all', 'rules' => [], 'is_active' => true,
        ]);
        $template = BroadcastTemplate::create([
            'branch_id' => $branch->id, 'code' => 'SMS1', 'name' => 'Promo', 'channel' => 'sms',
            'body' => 'Promo คอร์ส 30%', 'is_active' => true,
        ]);

        $campaign = BroadcastCampaign::create([
            'branch_id' => $branch->id, 'segment_id' => $segment->id, 'template_id' => $template->id,
            'name' => 'Test', 'status' => 'draft',
        ]);

        $this->app->make(BroadcastService::class)->sendNow($campaign);
        $campaign->refresh();

        $this->assertSame('completed', $campaign->status);
        $this->assertSame(3, $campaign->total_recipients);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(1, $campaign->skipped_count);
        $this->assertSame(0, $campaign->failed_count);
    }

    public function test_api_create_campaign_then_send(): void
    {
        [$branch] = $this->bootAdmin();
        Patient::factory()->count(2)->create(['branch_id' => $branch->id, 'phone' => '0811111111']);

        $segRes = $this->postJson('/api/v1/crm/segments', [
            'name' => 'All',
            'rules' => [],
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $tplRes = $this->postJson('/api/v1/crm/templates', [
            'code' => 'SMS1', 'name' => 'Promo', 'channel' => 'sms', 'body' => 'Hello {{first_name}}',
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $cRes = $this->postJson('/api/v1/crm/campaigns', [
            'name' => 'My Campaign',
            'segment_id' => $segRes->json('data.id'),
            'template_id' => $tplRes->json('data.id'),
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $campaignId = $cRes->json('data.id');
        $this->postJson("/api/v1/crm/campaigns/{$campaignId}/send", [], ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.sent_count', 2);
    }

    public function test_segment_preview_returns_count_and_samples(): void
    {
        [$branch] = $this->bootAdmin();
        Patient::factory()->count(5)->create(['branch_id' => $branch->id]);

        $segRes = $this->postJson('/api/v1/crm/segments', [
            'name' => 'all', 'rules' => [],
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $id = $segRes->json('data.id');

        $res = $this->getJson("/api/v1/crm/segments/{$id}/preview", ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertSame(5, $res->json('data.count'));
        $this->assertCount(5, $res->json('data.samples'));
    }
}
