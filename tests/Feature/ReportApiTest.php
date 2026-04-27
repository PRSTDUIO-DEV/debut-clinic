<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function bootAdminAndDay(): array
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->branches()->attach($branch->id, ['is_primary' => true]);
        $admin->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($admin);

        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $doctor = User::factory()->create(['branch_id' => $branch->id, 'is_doctor' => true]);
        $visit = Visit::factory()->create(['branch_id' => $branch->id, 'patient_id' => $patient->id]);

        $today = Carbon::today()->toDateString();
        $invoice = Invoice::create([
            'branch_id' => $branch->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id,
            'invoice_number' => 'INV-'.Str::random(6),
            'invoice_date' => $today,
            'subtotal' => 5000, 'discount_amount' => 0, 'vat_amount' => 0,
            'total_amount' => 5000, 'total_cogs' => 1000, 'total_commission' => 500,
            'status' => 'paid',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_type' => 'procedure',
            'item_id' => 1, 'item_name' => 'Botox 100u', 'quantity' => 1,
            'unit_price' => 5000, 'discount' => 0, 'total' => 5000, 'cost_price' => 1000,
            'doctor_id' => $doctor->id,
        ]);
        Payment::create([
            'branch_id' => $branch->id, 'invoice_id' => $invoice->id,
            'method' => 'cash', 'amount' => 5000, 'payment_date' => $today,
        ]);
        Expense::create([
            'branch_id' => $branch->id, 'expense_date' => $today,
            'amount' => 800, 'payment_method' => 'cash', 'description' => 'office',
        ]);

        return [$branch, $admin, $doctor, $today];
    }

    public function test_daily_pl_report_returns_expected_totals(): void
    {
        [$branch, , , $today] = $this->bootAdminAndDay();

        $res = $this->getJson("/api/v1/reports/daily-pl?date_from=$today&date_to=$today", ['X-Branch-Id' => $branch->id])
            ->assertOk();

        $totals = $res->json('data.totals');
        $this->assertSame(5000.0, (float) $totals['revenue']);
        $this->assertSame(1000.0, (float) $totals['cogs']);
        $this->assertSame(500.0, (float) $totals['commission']);
        $this->assertSame(0.0, (float) $totals['mdr']);
        $this->assertSame(800.0, (float) $totals['expenses']);
        $this->assertSame(3500.0, (float) $totals['gross_profit']);
        $this->assertSame(2700.0, (float) $totals['net_profit']);
    }

    public function test_doctor_performance_groups_correctly(): void
    {
        [$branch, , $doctor, $today] = $this->bootAdminAndDay();

        $res = $this->getJson("/api/v1/reports/doctor-performance?date_from=$today&date_to=$today", ['X-Branch-Id' => $branch->id])
            ->assertOk();

        $rows = collect($res->json('data.rows'))->keyBy('user_id');
        $this->assertArrayHasKey($doctor->id, $rows->toArray());
        $this->assertSame(5000.0, (float) $rows[$doctor->id]['revenue']);
        $this->assertSame(1, $rows[$doctor->id]['visits']);
    }

    public function test_procedure_performance_aggregates_by_item_id(): void
    {
        [$branch, , , $today] = $this->bootAdminAndDay();

        $res = $this->getJson("/api/v1/reports/procedure-performance?date_from=$today&date_to=$today", ['X-Branch-Id' => $branch->id])
            ->assertOk();

        $rows = $res->json('data.rows');
        $this->assertCount(1, $rows);
        $this->assertSame('Botox 100u', $rows[0]['name']);
        $this->assertSame(5000.0, (float) $rows[0]['revenue']);
        $this->assertSame(4000.0, (float) $rows[0]['gross']);
    }

    public function test_closing_prepare_then_commit_via_api(): void
    {
        [$branch, , , $today] = $this->bootAdminAndDay();

        $prep = $this->postJson('/api/v1/closings/prepare', ['date' => $today], ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $closingId = $prep->json('data.id');

        $this->assertSame(5000.0, (float) $prep->json('data.total_revenue'));
        // cash 5000 - cash expense 800 = 4200 expected
        $this->assertSame(4200.0, (float) $prep->json('data.expected_cash'));

        $commit = $this->postJson("/api/v1/closings/{$closingId}/commit", [
            'counted_cash' => 4200, 'notes' => 'all good',
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $this->assertSame('closed', $commit->json('data.status'));
        $this->assertSame(0.0, (float) $commit->json('data.variance'));
    }
}
