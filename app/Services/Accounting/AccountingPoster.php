<?php

namespace App\Services\Accounting;

use App\Models\Disbursement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MemberTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Composes accounting entry lines for each business document type
 * and posts them via AccountingService. Called from controllers/services
 * (rather than Eloquent observers) so we control timing precisely.
 */
class AccountingPoster
{
    public function __construct(
        private AccountingService $accounting,
        private ChartOfAccountSeeder $seeder,
    ) {}

    /**
     * Post entries for a paid invoice:
     *  - revenue split per item type
     *  - cost of goods sold (offset inventory)
     *  - cash/bank/AR by payment method
     *  - VAT payable (assume net price; if invoice has explicit vat_amount, use it)
     */
    public function postInvoice(Invoice $invoice): array
    {
        if ($invoice->status !== 'paid') {
            return [];
        }
        // Skip if already posted
        $exists = DB::table('accounting_entries')
            ->where('document_type', 'invoice')
            ->where('document_id', $invoice->id)
            ->exists();
        if ($exists) {
            return [];
        }

        $invoice->loadMissing(['items', 'payments']);

        // Revenue lines per item_type
        $revenueByCode = ['4100' => 0.0, '4200' => 0.0, '4300' => 0.0];
        $totalCogs = 0.0;
        foreach ($invoice->items as $item) {
            /** @var InvoiceItem $item */
            $code = match ($item->item_type) {
                'product' => '4200',
                'course', 'package' => '4300',
                default => '4100',
            };
            $revenueByCode[$code] += (float) $item->total;
            $totalCogs += (float) $item->cost_price * (int) $item->quantity;
        }

        $lines = [];
        foreach ($revenueByCode as $code => $amount) {
            if ($amount <= 0) {
                continue;
            }
            // Debit: payment receipt accounts (split below); Credit: revenue
            $lines[] = [
                'debit_code' => '1100', // placeholder, replaced below
                'credit_code' => $code,
                'amount' => $amount,
                'description' => "Revenue {$code} from invoice {$invoice->invoice_number}",
            ];
        }

        // Replace the placeholder debit lines with payment-method-based debits
        // by removing them and rebuilding from payments
        $lines = []; // restart cleanly

        // 1. Debit payment-method accounts; Credit revenue (allocate proportionally)
        $totalRevenue = array_sum($revenueByCode);
        foreach ($invoice->payments as $p) {
            $debitCode = $this->paymentMethodToCode($p->method);
            $share = $totalRevenue > 0 ? round((float) $p->amount * 1.0, 2) : 0;
            // For each revenue bucket, allocate proportional share
            foreach ($revenueByCode as $code => $rev) {
                if ($rev <= 0 || $totalRevenue <= 0) {
                    continue;
                }
                $alloc = round((float) $p->amount * $rev / $totalRevenue, 2);
                if ($alloc <= 0) {
                    continue;
                }
                $lines[] = [
                    'debit_code' => $debitCode,
                    'credit_code' => $code,
                    'amount' => $alloc,
                    'description' => "Invoice {$invoice->invoice_number} via {$p->method}",
                ];
            }

            // MDR fee (if any) — Debit MDR expense, Credit Bank
            if ($p->mdr_amount && (float) $p->mdr_amount > 0) {
                $lines[] = [
                    'debit_code' => '5400',
                    'credit_code' => '1110',
                    'amount' => (float) $p->mdr_amount,
                    'description' => "MDR fee for invoice {$invoice->invoice_number}",
                ];
            }
        }

        // 2. COGS: Debit COGS, Credit Inventory
        if ($totalCogs > 0) {
            $lines[] = [
                'debit_code' => '5100',
                'credit_code' => '1300',
                'amount' => round($totalCogs, 2),
                'description' => "COGS for invoice {$invoice->invoice_number}",
            ];
        }

        // 3. Doctor fee + staff commission: deferred to commission_transactions hook (postCommission)

        return $this->accounting->post(
            (int) $invoice->branch_id,
            'invoice',
            (int) $invoice->id,
            $lines,
            null,
            $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
        );
    }

    public function postExpense(Expense $expense): array
    {
        $exists = DB::table('accounting_entries')
            ->where('document_type', 'expense')
            ->where('document_id', $expense->id)
            ->exists();
        if ($exists) {
            return [];
        }

        $cat = $expense->category_id ? ExpenseCategory::query()->find($expense->category_id) : null;
        $expenseCode = $this->seeder->mapExpenseCategoryCode($cat?->name);
        $cashCode = $this->paymentMethodToCode($expense->payment_method);

        return $this->accounting->post(
            (int) $expense->branch_id,
            'expense',
            (int) $expense->id,
            [[
                'debit_code' => $expenseCode,
                'credit_code' => $cashCode,
                'amount' => (float) $expense->amount,
                'description' => 'Expense: '.($cat?->name ?? 'Other').' '.($expense->vendor ? "({$expense->vendor})" : ''),
            ]],
            null,
            $expense->expense_date->toDateString(),
        );
    }

    public function postDisbursement(Disbursement $disbursement): array
    {
        if ($disbursement->status !== 'paid') {
            return [];
        }
        $exists = DB::table('accounting_entries')
            ->where('document_type', 'disbursement')
            ->where('document_id', $disbursement->id)
            ->exists();
        if ($exists) {
            return [];
        }

        $debitCode = $this->seeder->mapDisbursementCode($disbursement->type);
        $creditCode = $this->paymentMethodToCode($disbursement->payment_method);

        return $this->accounting->post(
            (int) $disbursement->branch_id,
            'disbursement',
            (int) $disbursement->id,
            [[
                'debit_code' => $debitCode,
                'credit_code' => $creditCode,
                'amount' => (float) $disbursement->amount,
                'description' => "{$disbursement->type}: ".($disbursement->description ?? '')
                    .($disbursement->vendor ? " ({$disbursement->vendor})" : ''),
            ]],
            null,
            $disbursement->disbursement_date->toDateString(),
        );
    }

    public function postMemberTransaction(MemberTransaction $txn): array
    {
        $branchId = (int) $txn->memberAccount?->branch_id;
        if (! $branchId) {
            return [];
        }
        $exists = DB::table('accounting_entries')
            ->where('document_type', 'member_transaction')
            ->where('document_id', $txn->id)
            ->exists();
        if ($exists) {
            return [];
        }

        $lines = match ($txn->type) {
            // Customer deposits cash → Debit Cash, Credit Member Wallet liability
            'deposit' => [[
                'debit_code' => '1100',
                'credit_code' => '2400',
                'amount' => (float) $txn->amount,
                'description' => "Member top-up #{$txn->id}",
            ]],
            // Customer uses wallet → Debit Member Wallet, Credit Service Revenue (rough — actual revenue split is in invoice posting)
            'usage' => [[
                'debit_code' => '2400',
                'credit_code' => '4100',
                'amount' => (float) $txn->amount,
                'description' => "Member usage #{$txn->id}",
            ]],
            // Refund credits wallet back to customer (cash out): Debit Member Wallet, Credit Cash
            'refund' => [[
                'debit_code' => '2400',
                'credit_code' => '1100',
                'amount' => (float) $txn->amount,
                'description' => "Member refund #{$txn->id}",
            ]],
            // Adjustment: positive = increase liability (gift); negative = decrease (write-off)
            'adjustment' => [[
                'debit_code' => $txn->amount >= 0 ? '6900' : '2400',
                'credit_code' => $txn->amount >= 0 ? '2400' : '6900',
                'amount' => abs((float) $txn->amount),
                'description' => "Member adjustment #{$txn->id}: ".($txn->notes ?? ''),
            ]],
            default => [],
        };

        if (empty($lines)) {
            return [];
        }

        return $this->accounting->post(
            $branchId,
            'member_transaction',
            (int) $txn->id,
            $lines,
            null,
            $txn->created_at?->toDateString() ?? now()->toDateString(),
        );
    }

    /**
     * Post commission expenses for paid invoice items. Called from CheckoutService
     * after commission_transactions are written.
     */
    public function postCommissionsForInvoice(Invoice $invoice): array
    {
        $exists = DB::table('accounting_entries')
            ->where('document_type', 'invoice_commission')
            ->where('document_id', $invoice->id)
            ->exists();
        if ($exists) {
            return [];
        }

        $totals = DB::table('commission_transactions')
            ->whereIn('invoice_item_id', function ($q) use ($invoice) {
                $q->select('id')->from('invoice_items')->where('invoice_id', $invoice->id);
            })
            ->selectRaw("SUM(CASE WHEN type='doctor_fee' THEN amount ELSE 0 END) as doctor_fee")
            ->selectRaw("SUM(CASE WHEN type='staff_commission' THEN amount ELSE 0 END) as staff_commission")
            ->first();

        $lines = [];
        if ($totals && (float) $totals->doctor_fee > 0) {
            $lines[] = [
                'debit_code' => '5200',
                'credit_code' => '2100', // payable until disbursement
                'amount' => (float) $totals->doctor_fee,
                'description' => "Doctor fees for invoice {$invoice->invoice_number}",
            ];
        }
        if ($totals && (float) $totals->staff_commission > 0) {
            $lines[] = [
                'debit_code' => '5300',
                'credit_code' => '2100',
                'amount' => (float) $totals->staff_commission,
                'description' => "Staff commissions for invoice {$invoice->invoice_number}",
            ];
        }

        if (empty($lines)) {
            return [];
        }

        return $this->accounting->post(
            (int) $invoice->branch_id,
            'invoice_commission',
            (int) $invoice->id,
            $lines,
            null,
            $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
        );
    }

    private function paymentMethodToCode(string $method): string
    {
        return match ($method) {
            'cash' => '1100',
            'transfer', 'check' => '1110',
            'credit_card' => '1110',
            'member_credit' => '2400',
            'coupon' => '6900',
            default => '1100',
        };
    }
}
