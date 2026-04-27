<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MemberAccount;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\ChartOfAccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MisAndReportsTest extends TestCase
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

    public function test_mis_dashboard_returns_kpis_and_snapshot(): void
    {
        [$branch] = $this->bootAdmin();

        $res = $this->getJson('/api/v1/mis/dashboard?period=month', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $data = $res->json('data');
        $this->assertArrayHasKey('kpis', $data);
        $this->assertArrayHasKey('revenue', $data['kpis']);
        $this->assertArrayHasKey('snapshot', $data);
        $this->assertArrayHasKey('cash_on_hand', $data['snapshot']);
        $this->assertArrayHasKey('wallet_liability', $data['snapshot']);
    }

    public function test_mis_charts_returns_trend_for_n_days(): void
    {
        [$branch] = $this->bootAdmin();

        $res = $this->getJson('/api/v1/mis/charts?days=14', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $rows = $res->json('data.rows');
        $this->assertCount(14, $rows);
        $this->assertArrayHasKey('date', $rows[0]);
        $this->assertArrayHasKey('revenue', $rows[0]);
    }

    public function test_demographics_groups_by_gender_age_and_customer_group(): void
    {
        [$branch] = $this->bootAdmin();
        Patient::factory()->create(['branch_id' => $branch->id, 'gender' => 'male', 'date_of_birth' => '1985-01-15']);
        Patient::factory()->create(['branch_id' => $branch->id, 'gender' => 'female', 'date_of_birth' => '2000-05-20']);
        Patient::factory()->create(['branch_id' => $branch->id, 'gender' => 'female', 'date_of_birth' => null]);

        $res = $this->getJson('/api/v1/reports/demographics', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $data = $res->json('data');
        $this->assertSame(3, $data['total']);
        $this->assertSame(1, $data['by_gender']['male']);
        $this->assertSame(2, $data['by_gender']['female']);
        $this->assertSame(1, $data['by_age']['unknown']);
    }

    public function test_cohort_retention_groups_by_first_visit_month(): void
    {
        [$branch] = $this->bootAdmin();
        Patient::factory()->count(3)->create(['branch_id' => $branch->id, 'visit_count' => 3]);
        Patient::factory()->count(2)->create(['branch_id' => $branch->id, 'visit_count' => 1]);

        $res = $this->getJson('/api/v1/reports/cohort-retention?months=3', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $rows = $res->json('data.rows');
        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('cohort_size', $rows[0]);
    }

    public function test_birthday_this_month_filters_correctly(): void
    {
        [$branch] = $this->bootAdmin();
        $thisMonth = (int) now()->format('m');
        $otherMonth = $thisMonth === 12 ? 1 : $thisMonth + 1;

        Patient::factory()->create(['branch_id' => $branch->id, 'date_of_birth' => '1985-'.sprintf('%02d', $thisMonth).'-15']);
        Patient::factory()->create(['branch_id' => $branch->id, 'date_of_birth' => '1985-'.sprintf('%02d', $otherMonth).'-15']);

        $res = $this->getJson("/api/v1/reports/birthday-this-month?month={$thisMonth}", ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertCount(1, $res->json('data.rows'));
    }

    public function test_stock_value_snapshot_returns_warehouse_and_category_breakdown(): void
    {
        [$branch] = $this->bootAdmin();

        $res = $this->getJson('/api/v1/reports/stock-value', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $data = $res->json('data');
        $this->assertArrayHasKey('by_warehouse', $data);
        $this->assertArrayHasKey('by_category', $data);
    }

    public function test_top_procedures_endpoint_returns_sorted_list(): void
    {
        [$branch] = $this->bootAdmin();
        $today = now()->toDateString();

        $res = $this->getJson("/api/v1/mis/top-procedures?from={$today}&to={$today}", ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertIsArray($res->json('data'));
    }

    public function test_revenue_by_customer_group_endpoint(): void
    {
        [$branch] = $this->bootAdmin();
        $today = now()->toDateString();

        $res = $this->getJson("/api/v1/reports/revenue-by-customer-group?from={$today}&to={$today}", ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertArrayHasKey('rows', $res->json('data'));
        $this->assertArrayHasKey('totals', $res->json('data'));
    }

    public function test_wallet_outstanding_returns_active_balances(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        MemberAccount::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'balance' => 5000, 'total_deposit' => 10000, 'total_used' => 5000, 'status' => 'active',
        ]);

        $res = $this->getJson('/api/v1/reports/wallet-outstanding', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertCount(1, $res->json('data.rows'));
        $this->assertSame(5000.0, (float) $res->json('data.totals.balance'));
    }
}
