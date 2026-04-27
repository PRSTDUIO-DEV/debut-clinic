<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\CommissionTransaction;
use App\Models\CompensationRule;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Services\Accounting\ChartOfAccountSeeder;
use App\Services\Hr\CompensationService;
use App\Services\Hr\PayrollService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompensationAndPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        if (! Branch::query()->exists()) {
            $branch = Branch::factory()->create();
            app(ChartOfAccountSeeder::class)->seed($branch->id);
        }
    }

    private function makeInvoiceItem(int $branchId, int $userId): InvoiceItem
    {
        $patient = Patient::factory()->create(['branch_id' => $branchId]);
        $visit = Visit::factory()->create(['branch_id' => $branchId, 'patient_id' => $patient->id]);
        $invoice = Invoice::create([
            'branch_id' => $branchId, 'visit_id' => $visit->id, 'patient_id' => $patient->id,
            'invoice_number' => 'INV-T-'.uniqid(), 'invoice_date' => now()->toDateString(),
            'subtotal' => 1000, 'total_amount' => 1000, 'status' => 'paid',
        ]);

        $proc = Procedure::factory()->create(['branch_id' => $branchId]);

        return InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_type' => 'procedure', 'item_id' => $proc->id,
            'item_name' => 'Test', 'unit_price' => 1000, 'quantity' => 1, 'total' => 1000,
            'cost_price' => 0, 'doctor_id' => $userId,
        ]);
    }

    public function test_user_specific_rule_wins_over_role_rule(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $role = Role::where('name', 'doctor')->first();
        $user->roles()->attach($role->id);

        CompensationRule::create([
            'branch_id' => $branch->id, 'role_id' => $role->id,
            'type' => 'monthly', 'base_amount' => 30000,
            'valid_from' => now()->subYear()->toDateString(), 'is_active' => true,
        ]);
        CompensationRule::create([
            'branch_id' => $branch->id, 'user_id' => $user->id,
            'type' => 'monthly', 'base_amount' => 50000,
            'valid_from' => now()->subMonth()->toDateString(), 'is_active' => true,
        ]);

        $rule = app(CompensationService::class)->resolveRate($user);
        $this->assertNotNull($rule);
        $this->assertSame($user->id, $rule->user_id);
        $this->assertSame('50000.00', (string) $rule->base_amount);
    }

    public function test_role_rule_used_when_no_user_rule(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $role = Role::where('name', 'nurse')->first();
        $user->roles()->attach($role->id);

        CompensationRule::create([
            'branch_id' => $branch->id, 'role_id' => $role->id,
            'type' => 'monthly', 'base_amount' => 18000,
            'valid_from' => now()->subYear()->toDateString(), 'is_active' => true,
        ]);

        $rule = app(CompensationService::class)->resolveRate($user);
        $this->assertNotNull($rule);
        $this->assertSame($role->id, $rule->role_id);
    }

    public function test_compute_base_pay_for_each_type(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $monthly = CompensationRule::create([
            'branch_id' => $branch->id, 'user_id' => $user->id,
            'type' => 'monthly', 'base_amount' => 30000,
            'valid_from' => now()->toDateString(), 'is_active' => true,
        ]);
        $hourly = CompensationRule::create([
            'branch_id' => $branch->id, 'user_id' => $user->id,
            'type' => 'hourly', 'base_amount' => 200,
            'valid_from' => now()->toDateString(), 'is_active' => true,
        ]);
        $daily = CompensationRule::create([
            'branch_id' => $branch->id, 'user_id' => $user->id,
            'type' => 'daily', 'base_amount' => 1500,
            'valid_from' => now()->toDateString(), 'is_active' => true,
        ]);

        $svc = app(CompensationService::class);
        $this->assertSame(30000.0, $svc->computeBasePay($monthly, 160, 22));
        $this->assertSame(32000.0, $svc->computeBasePay($hourly, 160, 22)); // 200 * 160
        $this->assertSame(33000.0, $svc->computeBasePay($daily, 160, 22)); // 1500 * 22
    }

    public function test_payroll_preview_includes_base_and_commission(): void
    {
        $branch = Branch::factory()->create();
        app(ChartOfAccountSeeder::class)->seed($branch->id);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);

        CompensationRule::create([
            'branch_id' => $branch->id, 'user_id' => $user->id,
            'type' => 'monthly', 'base_amount' => 30000,
            'valid_from' => now()->startOfMonth()->toDateString(), 'is_active' => true,
        ]);

        $now = now();
        $item = $this->makeInvoiceItem($branch->id, $user->id);
        CommissionTransaction::create([
            'branch_id' => $branch->id, 'invoice_item_id' => $item->id, 'user_id' => $user->id,
            'type' => 'doctor_fee', 'base_amount' => 10000, 'rate_pct' => 50, 'amount' => 5000,
            'commission_date' => $now->copy()->startOfMonth()->addDays(5)->toDateString(),
            'is_paid' => false,
        ]);

        $payroll = app(PayrollService::class)->generatePreview($branch->id, (int) $now->format('Y'), (int) $now->format('m'));
        $this->assertSame('draft', $payroll->status);
        $this->assertCount(1, $payroll->items);
        $item = $payroll->items->first();
        $this->assertSame('30000.00', (string) $item->base_pay);
        $this->assertSame('5000.00', (string) $item->commission_total);
        $this->assertSame('35000.00', (string) $item->net_pay);
    }

    public function test_finalize_marks_commissions_as_paid(): void
    {
        $branch = Branch::factory()->create();
        app(ChartOfAccountSeeder::class)->seed($branch->id);
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);

        $now = now();
        $item = $this->makeInvoiceItem($branch->id, $user->id);
        $cm = CommissionTransaction::create([
            'branch_id' => $branch->id, 'invoice_item_id' => $item->id, 'user_id' => $user->id,
            'type' => 'doctor_fee', 'base_amount' => 2000, 'rate_pct' => 50, 'amount' => 1000,
            'commission_date' => $now->copy()->startOfMonth()->toDateString(),
            'is_paid' => false,
        ]);

        $payroll = app(PayrollService::class)->generatePreview($branch->id, (int) $now->format('Y'), (int) $now->format('m'));
        $payroll = app(PayrollService::class)->finalize($payroll, $admin);

        $this->assertSame('finalized', $payroll->status);
        $this->assertTrue((bool) $cm->fresh()->is_paid);
    }

    public function test_adjust_item_recomputes_net_pay(): void
    {
        $branch = Branch::factory()->create();
        app(ChartOfAccountSeeder::class)->seed($branch->id);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);

        CompensationRule::create([
            'branch_id' => $branch->id, 'user_id' => $user->id,
            'type' => 'monthly', 'base_amount' => 20000,
            'valid_from' => now()->startOfMonth()->toDateString(), 'is_active' => true,
        ]);

        $payroll = app(PayrollService::class)->generatePreview($branch->id, (int) now()->format('Y'), (int) now()->format('m'));
        $item = $payroll->items->first();

        $svc = app(PayrollService::class);
        $svc->adjustItem($item, 3000, 500);
        $payroll->refresh();
        $item->refresh();

        $this->assertSame('22500.00', (string) $item->net_pay); // 20000 + 0 + 0 + 3000 - 500
        $this->assertSame('22500.00', (string) $payroll->total_amount);
    }
}
