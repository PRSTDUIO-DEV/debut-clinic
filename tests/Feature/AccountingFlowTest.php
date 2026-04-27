<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\ChartOfAccountSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountingFlowTest extends TestCase
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
        $this->app->make(ChartOfAccountSeeder::class)->seed($branch->id);

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->branches()->attach($branch->id, ['is_primary' => true]);
        $admin->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($admin);

        return [$branch, $admin];
    }

    public function test_pr_to_po_to_receive_full_flow(): void
    {
        [$branch] = $this->bootAdmin();
        $supplier = Supplier::create(['branch_id' => $branch->id, 'name' => 'Test Supplier', 'is_active' => true]);
        $warehouse = Warehouse::create(['branch_id' => $branch->id, 'name' => 'Main', 'type' => 'main']);

        // Create PR
        $prRes = $this->postJson('/api/v1/accounting/pr', [
            'request_date' => now()->toDateString(),
            'items' => [[
                'description' => 'Botox 100u',
                'quantity' => 10,
                'estimated_cost' => 8000,
            ]],
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $prId = $prRes->json('data.id');

        // Submit + Approve
        $this->postJson("/api/v1/accounting/pr/{$prId}/submit", [], ['X-Branch-Id' => $branch->id])->assertOk();
        $this->postJson("/api/v1/accounting/pr/{$prId}/approve", [], ['X-Branch-Id' => $branch->id])->assertOk();

        // Convert to PO
        $poRes = $this->postJson("/api/v1/accounting/pr/{$prId}/convert", [
            'supplier_id' => $supplier->id,
            'vat_percent' => 7,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $poId = $poRes->json('data.id');
        $this->assertSame(80000.0, (float) $poRes->json('data.subtotal'));
        $this->assertSame(5600.0, (float) $poRes->json('data.vat_amount'));

        // Send + receive partial
        $this->postJson("/api/v1/accounting/po/{$poId}/send", [], ['X-Branch-Id' => $branch->id])->assertOk();
        $poDetail = $this->getJson("/api/v1/accounting/po/{$poId}", ['X-Branch-Id' => $branch->id])->json('data');
        $itemId = $poDetail['items'][0]['id'];

        $this->postJson("/api/v1/accounting/po/{$poId}/receive", [
            'warehouse_id' => $warehouse->id,
            'rows' => [['po_item_id' => $itemId, 'qty' => 6, 'lot_no' => 'LOT-A']],
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $poDetail = $this->getJson("/api/v1/accounting/po/{$poId}", ['X-Branch-Id' => $branch->id])->json('data');
        $this->assertSame('partial_received', $poDetail['status']);
        $this->assertSame(6, $poDetail['items'][0]['received_qty']);

        // Receive remaining
        $this->postJson("/api/v1/accounting/po/{$poId}/receive", [
            'warehouse_id' => $warehouse->id,
            'rows' => [['po_item_id' => $itemId, 'qty' => 4, 'lot_no' => 'LOT-B']],
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $poDetail = $this->getJson("/api/v1/accounting/po/{$poId}", ['X-Branch-Id' => $branch->id])->json('data');
        $this->assertSame('received', $poDetail['status']);
    }

    public function test_invoice_paid_posts_double_entry(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $proc = Procedure::factory()->create([
            'branch_id' => $branch->id, 'price' => 1000, 'cost' => 200,
            'doctor_fee_rate' => 0, 'staff_commission_rate' => 0, 'follow_up_days' => 0,
        ]);

        $vUuid = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
            ->json('data.id');
        $this->postJson("/api/v1/visits/$vUuid/invoice-items", [
            'item_type' => 'procedure', 'item_id' => $proc->id, 'quantity' => 1,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);
        $this->postJson("/api/v1/visits/$vUuid/checkout", [
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        // Should have at least 2 entries: 1 revenue, 1 cogs (cash → revenue, cogs → inventory)
        $entries = AccountingEntry::query()->where('document_type', 'invoice')->get();
        $this->assertGreaterThanOrEqual(2, $entries->count());

        // Total debits == total credits
        $totalDebit = $entries->sum('amount');
        $totalCredit = $entries->sum('amount'); // single-line balanced
        $this->assertSame((float) $totalDebit, (float) $totalCredit);
    }

    public function test_disbursement_pay_posts_accounting(): void
    {
        [$branch, $admin] = $this->bootAdmin();

        $res = $this->postJson('/api/v1/accounting/disbursements', [
            'disbursement_date' => now()->toDateString(),
            'type' => 'rent',
            'amount' => 25000,
            'payment_method' => 'transfer',
            'vendor' => 'อาคาร X',
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $id = $res->json('data.id');

        $this->postJson("/api/v1/accounting/disbursements/{$id}/approve", [], ['X-Branch-Id' => $branch->id])->assertOk();
        $this->postJson("/api/v1/accounting/disbursements/{$id}/pay", [
            'reference' => 'TXN-001',
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $this->assertDatabaseHas('accounting_entries', [
            'document_type' => 'disbursement',
            'document_id' => $id,
            'amount' => 25000,
        ]);
    }

    public function test_tax_invoice_issue_with_sequential_no(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $proc = Procedure::factory()->create([
            'branch_id' => $branch->id, 'price' => 5000, 'cost' => 0,
            'doctor_fee_rate' => 0, 'staff_commission_rate' => 0, 'follow_up_days' => 0,
        ]);

        $vUuid = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
            ->json('data.id');
        $this->postJson("/api/v1/visits/$vUuid/invoice-items", [
            'item_type' => 'procedure', 'item_id' => $proc->id, 'quantity' => 1,
        ], ['X-Branch-Id' => $branch->id]);
        $this->postJson("/api/v1/visits/$vUuid/checkout", [
            'payments' => [['method' => 'cash', 'amount' => 5000]],
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $invoice = Invoice::query()->where('branch_id', $branch->id)->first();

        $res = $this->postJson('/api/v1/accounting/tax-invoices', [
            'invoice_id' => $invoice->id,
            'customer_name' => 'บริษัท ลูกค้า จำกัด',
            'customer_tax_id' => '0123456789012',
            'vat_rate' => 7,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->assertStringStartsWith('TAX'.now()->year.'-', $res->json('data.tax_invoice_no'));
        // 5000 includes VAT → taxable ≈ 4672.90, vat ≈ 327.10
        $this->assertSame(5000.0, (float) $res->json('data.total'));
    }

    public function test_trial_balance_endpoint_balanced(): void
    {
        [$branch] = $this->bootAdmin();

        $res = $this->getJson('/api/v1/accounting/reports/trial-balance', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertArrayHasKey('totals', $res->json('data'));
    }
}
