<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Influencer;
use App\Models\InfluencerCampaign;
use App\Models\InfluencerReferral;
use App\Models\LineRichMenu;
use App\Models\Patient;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Services\Accounting\ChartOfAccountSeeder;
use App\Services\Marketing\ReviewService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketingFlowTest extends TestCase
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
        app(ChartOfAccountSeeder::class)->seed($branch->id);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->branches()->attach($branch->id, ['is_primary' => true]);
        $admin->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($admin);

        return [$branch, $admin];
    }

    public function test_coupon_index_and_bulk_generate(): void
    {
        [$branch] = $this->bootAdmin();

        $res = $this->postJson('/api/v1/marketing/coupons/generate', [
            'count' => 5, 'prefix' => 'BTX',
            'name' => 'BTX Promo', 'type' => 'percent', 'value' => 15,
            'valid_from' => now()->toDateString(),
            'valid_to' => now()->addMonth()->toDateString(),
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->assertCount(5, $res->json('data'));
        $this->assertSame(5, Coupon::where('branch_id', $branch->id)->count());

        $list = $this->getJson('/api/v1/marketing/coupons', ['X-Branch-Id' => $branch->id])
            ->assertOk()->json('data.data');
        $this->assertCount(5, $list);
    }

    public function test_coupon_validate_returns_discount(): void
    {
        [$branch] = $this->bootAdmin();
        Coupon::create([
            'branch_id' => $branch->id, 'code' => 'TEST20', 'name' => 'X',
            'type' => 'percent', 'value' => 20, 'is_active' => true,
            'valid_from' => now()->toDateString(), 'valid_to' => now()->addDay()->toDateString(),
            'max_per_customer' => 1,
        ]);

        $r = $this->postJson('/api/v1/marketing/coupons/validate', [
            'code' => 'TEST20', 'subtotal' => 5000,
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $this->assertSame(1000.0, (float) $r->json('data.discount'));
    }

    public function test_promotion_active_filters_by_date(): void
    {
        [$branch] = $this->bootAdmin();
        Promotion::create([
            'branch_id' => $branch->id, 'name' => 'Active', 'type' => 'percent',
            'rules' => ['value' => 10], 'valid_from' => now()->toDateString(),
            'valid_to' => now()->addDay()->toDateString(), 'is_active' => true,
        ]);
        Promotion::create([
            'branch_id' => $branch->id, 'name' => 'Expired', 'type' => 'percent',
            'rules' => ['value' => 10], 'valid_from' => now()->subMonth()->toDateString(),
            'valid_to' => now()->subDay()->toDateString(), 'is_active' => true,
        ]);

        $list = $this->getJson('/api/v1/marketing/promotions/active', ['X-Branch-Id' => $branch->id])
            ->assertOk()->json('data');
        $this->assertCount(1, $list);
        $this->assertSame('Active', $list[0]['name']);
    }

    public function test_influencer_create_and_campaign_with_shortcode(): void
    {
        [$branch] = $this->bootAdmin();
        $r1 = $this->postJson('/api/v1/marketing/influencers', [
            'name' => 'IG Demo', 'channel' => 'instagram', 'commission_rate' => 5,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $infId = $r1->json('data.id');

        $r2 = $this->postJson("/api/v1/marketing/influencers/{$infId}/campaigns", [
            'name' => 'Spring Promo', 'utm_source' => 'instagram', 'utm_campaign' => 'spring',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'total_budget' => 10000, 'status' => 'active',
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $shortcode = $r2->json('data.shortcode');
        $this->assertNotEmpty($shortcode);
        $this->assertSame(6, strlen($shortcode));
    }

    public function test_utm_landing_creates_referral(): void
    {
        [$branch] = $this->bootAdmin();
        $inf = Influencer::create(['branch_id' => $branch->id, 'name' => 'X', 'channel' => 'instagram']);
        $camp = InfluencerCampaign::create([
            'branch_id' => $branch->id, 'influencer_id' => $inf->id,
            'name' => 'C', 'shortcode' => 'abcdef',
            'utm_source' => 'ig', 'utm_campaign' => 'c1',
            'landing_url' => 'https://example.com',
            'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'status' => 'active',
        ]);

        $this->get('/r/abcdef')->assertRedirect();
        $this->assertSame(1, InfluencerReferral::where('campaign_id', $camp->id)->count());
    }

    public function test_campaign_report_computes_roi(): void
    {
        [$branch] = $this->bootAdmin();
        $inf = Influencer::create(['branch_id' => $branch->id, 'name' => 'X', 'channel' => 'instagram']);
        $camp = InfluencerCampaign::create([
            'branch_id' => $branch->id, 'influencer_id' => $inf->id,
            'name' => 'C', 'shortcode' => 'abcdeg',
            'utm_source' => 'ig', 'utm_campaign' => 'c1',
            'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'total_budget' => 10000, 'status' => 'active',
        ]);
        $patient = Patient::factory()->create(['branch_id' => $branch->id, 'total_spent' => 25000]);
        InfluencerReferral::create([
            'campaign_id' => $camp->id, 'patient_id' => $patient->id,
            'referred_at' => now(), 'lifetime_value' => 25000,
        ]);

        $r = $this->getJson("/api/v1/marketing/campaigns/{$camp->id}/report", ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertSame(1, $r->json('data.signups'));
        $this->assertSame(150.0, (float) $r->json('data.roi_pct')); // (25000-10000)/10000*100
    }

    public function test_review_request_and_public_submit_publishes_high_rating(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $visit = Visit::factory()->create(['branch_id' => $branch->id, 'patient_id' => $patient->id]);

        $review = app(ReviewService::class)->requestReview($visit);
        $this->assertSame('pending', $review->status);
        $this->assertNotEmpty($review->public_token);

        $this->postJson("/api/v1/public/reviews/{$review->public_token}", [
            'rating' => 5, 'title' => 'ดีมาก', 'body' => 'หมอใจดี',
        ])->assertCreated();

        $this->assertSame('published', Review::find($review->id)->status);
    }

    public function test_review_low_rating_stays_pending_for_moderation(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $visit = Visit::factory()->create(['branch_id' => $branch->id, 'patient_id' => $patient->id]);
        $review = app(ReviewService::class)->requestReview($visit);

        $this->postJson("/api/v1/public/reviews/{$review->public_token}", [
            'rating' => 2, 'body' => 'ไม่ประทับใจ',
        ])->assertCreated();

        $this->assertSame('pending', Review::find($review->id)->status);
    }

    public function test_review_aggregate_returns_avg_and_distribution(): void
    {
        [$branch, $admin] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        foreach ([5, 4, 5, 3, 5] as $rating) {
            Review::create([
                'branch_id' => $branch->id, 'patient_id' => $patient->id,
                'rating' => $rating, 'source' => 'line',
                'status' => 'published', 'submitted_at' => now(),
            ]);
        }

        $r = $this->getJson('/api/v1/reviews/aggregate', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertSame(4.4, (float) $r->json('data.branch.avg'));
        $this->assertSame(5, $r->json('data.branch.count'));
        $this->assertSame(3, $r->json('data.branch.distribution.5'));
    }

    public function test_rich_menu_layout_validation_and_active_swap(): void
    {
        [$branch] = $this->bootAdmin();

        // Wrong button count → 422
        $this->postJson('/api/v1/line/rich-menus', [
            'name' => 'Bad', 'layout' => 'compact_6',
            'buttons' => [['label' => 'A', 'action' => 'url', 'value' => '/']],
        ], ['X-Branch-Id' => $branch->id])->assertStatus(422);

        // Correct count → 201
        $r = $this->postJson('/api/v1/line/rich-menus', [
            'name' => 'M1', 'layout' => 'compact_4',
            'buttons' => array_map(fn ($i) => ['label' => "B$i", 'action' => 'url', 'value' => '/b/'.$i], range(1, 4)),
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $id = $r->json('data.id');

        $this->postJson("/api/v1/line/rich-menus/{$id}/sync", [], ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertTrue(LineRichMenu::find($id)->is_active);
    }
}
