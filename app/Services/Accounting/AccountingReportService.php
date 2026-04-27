<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    /**
     * General ledger for an account in [from, to].
     *
     * @return array{account: array, opening: float, rows: array<int, array>, closing: float, totals: array}
     */
    public function generalLedger(int $branchId, int $accountId, string $from, string $to): array
    {
        $account = ChartOfAccount::query()->where('branch_id', $branchId)->findOrFail($accountId);

        // Opening balance: sum of debits-credits on this account before $from
        $opening = $this->balanceAsOf($branchId, $accountId, $from, exclusive: true);

        $entries = DB::table('accounting_entries')
            ->where('branch_id', $branchId)
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $to)
            ->where(function ($q) use ($accountId) {
                $q->where('debit_account_id', $accountId)
                    ->orWhere('credit_account_id', $accountId);
            })
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get(['id', 'entry_date', 'journal_no', 'document_type', 'document_id', 'debit_account_id', 'credit_account_id', 'amount', 'description']);

        $rows = [];
        $running = $opening;
        $debitTotal = 0.0;
        $creditTotal = 0.0;
        foreach ($entries as $e) {
            $debit = (int) $e->debit_account_id === $accountId ? (float) $e->amount : 0;
            $credit = (int) $e->credit_account_id === $accountId ? (float) $e->amount : 0;
            // Direction follows account type: assets/expenses increase by debit, others by credit
            $isDebitNature = in_array($account->type, ['asset', 'expense'], true);
            $running += $isDebitNature ? ($debit - $credit) : ($credit - $debit);
            $debitTotal += $debit;
            $creditTotal += $credit;

            $rows[] = [
                'id' => $e->id,
                'date' => $e->entry_date,
                'journal_no' => $e->journal_no,
                'document' => $e->document_type.($e->document_id ? "#{$e->document_id}" : ''),
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'description' => $e->description,
                'running_balance' => round($running, 2),
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ],
            'period' => ['from' => $from, 'to' => $to],
            'opening' => round($opening, 2),
            'rows' => $rows,
            'closing' => round($running, 2),
            'totals' => ['debit' => round($debitTotal, 2), 'credit' => round($creditTotal, 2)],
        ];
    }

    /**
     * Trial balance — every account with debit/credit totals + ending balance as of date.
     */
    public function trialBalance(int $branchId, string $asOf): array
    {
        $accounts = ChartOfAccount::query()
            ->where('branch_id', $branchId)
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($accounts as $a) {
            $balance = $this->balanceAsOf($branchId, $a->id, $asOf);
            $isDebitNature = in_array($a->type, ['asset', 'expense'], true);
            $debit = $isDebitNature ? max(0, $balance) : max(0, -$balance);
            $credit = ! $isDebitNature ? max(0, $balance) : max(0, -$balance);
            // Skip zero rows
            if ($debit == 0 && $credit == 0) {
                continue;
            }
            $rows[] = [
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
            ];
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return [
            'as_of' => $asOf,
            'rows' => $rows,
            'totals' => [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
                'balanced' => round($totalDebit, 2) === round($totalCredit, 2),
            ],
        ];
    }

    public function cashFlow(int $branchId, string $from, string $to): array
    {
        $cashAccounts = ChartOfAccount::query()
            ->where('branch_id', $branchId)
            ->whereIn('code', ['1100', '1110'])
            ->get();

        $rows = [];
        $totalIn = 0.0;
        $totalOut = 0.0;
        foreach ($cashAccounts as $a) {
            $opening = $this->balanceAsOf($branchId, $a->id, $from, exclusive: true);
            $debits = (float) DB::table('accounting_entries')
                ->where('branch_id', $branchId)
                ->where('debit_account_id', $a->id)
                ->whereDate('entry_date', '>=', $from)
                ->whereDate('entry_date', '<=', $to)
                ->sum('amount');
            $credits = (float) DB::table('accounting_entries')
                ->where('branch_id', $branchId)
                ->where('credit_account_id', $a->id)
                ->whereDate('entry_date', '>=', $from)
                ->whereDate('entry_date', '<=', $to)
                ->sum('amount');
            $closing = $opening + $debits - $credits;

            $rows[] = [
                'code' => $a->code,
                'name' => $a->name,
                'opening' => round($opening, 2),
                'cash_in' => round($debits, 2),
                'cash_out' => round($credits, 2),
                'closing' => round($closing, 2),
                'net' => round($debits - $credits, 2),
            ];
            $totalIn += $debits;
            $totalOut += $credits;
        }

        return [
            'period' => ['from' => $from, 'to' => $to],
            'rows' => $rows,
            'totals' => [
                'cash_in' => round($totalIn, 2),
                'cash_out' => round($totalOut, 2),
                'net' => round($totalIn - $totalOut, 2),
            ],
        ];
    }

    public function taxSummary(int $branchId, string $month): array
    {
        $start = $month.'-01';
        $end = date('Y-m-t', strtotime($start));

        // Output VAT (sales): tax_invoices.vat_amount where status=active in month
        $output = (float) DB::table('tax_invoices')
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->whereDate('issued_at', '>=', $start)
            ->whereDate('issued_at', '<=', $end)
            ->sum('vat_amount');

        // Output detail
        $outputRows = DB::table('tax_invoices')
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->whereDate('issued_at', '>=', $start)
            ->whereDate('issued_at', '<=', $end)
            ->orderBy('issued_at')
            ->get(['id', 'tax_invoice_no', 'issued_at', 'customer_name', 'customer_tax_id', 'taxable_amount', 'vat_amount', 'total']);

        // Input VAT (purchases): from purchase_orders received in month, vat_amount
        $input = (float) DB::table('purchase_orders')
            ->where('branch_id', $branchId)
            ->whereIn('status', ['received', 'partial_received'])
            ->whereDate('order_date', '>=', $start)
            ->whereDate('order_date', '<=', $end)
            ->sum('vat_amount');

        return [
            'period' => $month,
            'output_vat' => round($output, 2),
            'input_vat' => round($input, 2),
            'net_payable' => round($output - $input, 2),
            'output_rows' => $outputRows,
        ];
    }

    /**
     * Balance of an account as of a date (inclusive by default).
     */
    public function balanceAsOf(int $branchId, int $accountId, string $date, bool $exclusive = false): float
    {
        $op = $exclusive ? '<' : '<=';

        $debit = (float) DB::table('accounting_entries')
            ->where('branch_id', $branchId)
            ->where('debit_account_id', $accountId)
            ->whereDate('entry_date', $op, $date)
            ->sum('amount');
        $credit = (float) DB::table('accounting_entries')
            ->where('branch_id', $branchId)
            ->where('credit_account_id', $accountId)
            ->whereDate('entry_date', $op, $date)
            ->sum('amount');

        $account = ChartOfAccount::query()->find($accountId);
        $isDebitNature = $account && in_array($account->type, ['asset', 'expense'], true);

        return $isDebitNature ? ($debit - $credit) : ($credit - $debit);
    }
}
