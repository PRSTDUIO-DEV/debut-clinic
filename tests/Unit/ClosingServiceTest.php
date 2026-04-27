<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use App\Services\ClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClosingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bootDay(string $date): array
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $visit = Visit::factory()->create(['branch_id' => $branch->id, 'patient_id' => $patient->id]);

        $invoice = Invoice::create([
            'branch_id' => $branch->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id,
            'invoice_number' => 'INV-'.Str::random(6),
            'invoice_date' => $date,
            'subtotal' => 1000, 'discount_amount' => 0, 'vat_amount' => 0,
            'total_amount' => 1000, 'total_cogs' => 200, 'total_commission' => 100,
            'status' => 'paid',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_type' => 'procedure', 'item_id' => 0,
            'item_name' => 'X', 'quantity' => 1, 'unit_price' => 1000,
            'discount' => 0, 'total' => 1000, 'cost_price' => 200,
        ]);
        Payment::create([
            'branch_id' => $branch->id, 'invoice_id' => $invoice->id,
            'method' => 'cash', 'amount' => 600, 'payment_date' => $date,
        ]);
        Payment::create([
            'branch_id' => $branch->id, 'invoice_id' => $invoice->id,
            'method' => 'credit_card', 'amount' => 400,
            'mdr_rate' => 2.0, 'mdr_amount' => 8.0,
            'payment_date' => $date,
        ]);
        Expense::create([
            'branch_id' => $branch->id, 'expense_date' => $date,
            'amount' => 100, 'payment_method' => 'cash', 'description' => 'office snacks',
        ]);

        return [$branch, $patient, $visit, $invoice];
    }

    public function test_prepare_computes_correct_snapshot(): void
    {
        $date = now()->toDateString();
        [$branch] = $this->bootDay($date);

        $closing = $this->app->make(ClosingService::class)->prepare($branch->id, $date);

        $this->assertSame('draft', $closing->status);
        $this->assertSame('1000.00', (string) $closing->total_revenue);
        $this->assertSame('200.00', (string) $closing->total_cogs);
        $this->assertSame('100.00', (string) $closing->total_commission);
        $this->assertSame('8.00', (string) $closing->total_mdr);
        $this->assertSame('100.00', (string) $closing->total_expenses);
        // gross = 1000 - 200 - 100 - 8 = 692
        $this->assertSame('692.00', (string) $closing->gross_profit);
        // net = 692 - 100 = 592
        $this->assertSame('592.00', (string) $closing->net_profit);
        // expected cash = 600 (cash payments) - 100 (cash expenses) = 500
        $this->assertSame('500.00', (string) $closing->expected_cash);
        $this->assertSame(600.0, (float) $closing->payment_breakdown['cash']);
        $this->assertSame(400.0, (float) $closing->payment_breakdown['credit_card']);
    }

    public function test_commit_calculates_variance_and_locks(): void
    {
        $date = now()->toDateString();
        [$branch] = $this->bootDay($date);
        $svc = $this->app->make(ClosingService::class);
        $closing = $svc->prepare($branch->id, $date);
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $svc->commit($closing, 480, $user, 'short by 20');
        $closing->refresh();

        $this->assertSame('closed', $closing->status);
        $this->assertSame('480.00', (string) $closing->counted_cash);
        $this->assertSame('-20.00', (string) $closing->variance);
        $this->assertSame($user->id, $closing->closed_by);

        $this->expectException(ValidationException::class);
        $svc->commit($closing, 500);
    }

    public function test_prepare_idempotent_when_already_closed(): void
    {
        $date = now()->toDateString();
        [$branch] = $this->bootDay($date);
        $svc = $this->app->make(ClosingService::class);
        $closing = $svc->prepare($branch->id, $date);
        $svc->commit($closing, 500);

        $closing2 = $svc->prepare($branch->id, $date);
        $this->assertSame('closed', $closing2->status);
        $this->assertSame($closing->id, $closing2->id);
    }

    public function test_reopen_requires_closed_status(): void
    {
        $date = now()->toDateString();
        [$branch] = $this->bootDay($date);
        $svc = $this->app->make(ClosingService::class);
        $closing = $svc->prepare($branch->id, $date);

        $this->expectException(ValidationException::class);
        $svc->reopen($closing, 'oops');
    }

    public function test_auto_prepare_yesterday_creates_drafts_for_all_branches(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        Carbon::setTestNow('2026-04-26 09:00:00');

        $svc = $this->app->make(ClosingService::class);
        $count = $svc->autoPrepareYesterday();

        $this->assertGreaterThanOrEqual(2, $count);
        foreach ([$branchA->id, $branchB->id] as $bid) {
            $row = DailyClosing::query()->where('branch_id', $bid)->first();
            $this->assertNotNull($row);
            $this->assertSame('2026-04-25', $row->closing_date->toDateString());
            $this->assertSame('draft', $row->status);
        }

        Carbon::setTestNow();
    }
}
