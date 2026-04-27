<?php

namespace App\Services\Accounting;

use App\Models\Invoice;
use App\Models\TaxInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxInvoiceService
{
    public function nextNumber(int $branchId, ?\DateTimeInterface $date = null): string
    {
        $year = ($date ?: now())->format('Y');
        $prefix = 'TAX'.$year.'-';

        return DB::transaction(function () use ($branchId, $prefix) {
            $last = TaxInvoice::query()
                ->where('branch_id', $branchId)
                ->where('tax_invoice_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('tax_invoice_no')
                ->value('tax_invoice_no');
            $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

            return $prefix.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        });
    }

    public function issue(
        Invoice $invoice,
        string $customerName,
        ?string $customerTaxId = null,
        ?string $customerAddress = null,
        float $vatRate = 7.0,
        ?User $user = null,
    ): TaxInvoice {
        if ($invoice->status !== 'paid') {
            throw ValidationException::withMessages(['invoice' => 'Invoice must be paid']);
        }
        $exists = TaxInvoice::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'active')
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['invoice' => 'Active tax invoice already exists for this invoice']);
        }

        // Compute taxable amount: assume invoice total includes VAT
        $total = round((float) $invoice->total_amount, 2);
        $taxable = round($total / (1 + $vatRate / 100), 2);
        $vat = round($total - $taxable, 2);

        return TaxInvoice::create([
            'branch_id' => $invoice->branch_id,
            'invoice_id' => $invoice->id,
            'tax_invoice_no' => $this->nextNumber((int) $invoice->branch_id),
            'issued_at' => now()->toDateString(),
            'customer_name' => $customerName,
            'customer_tax_id' => $customerTaxId,
            'customer_address' => $customerAddress,
            'taxable_amount' => $taxable,
            'vat_rate' => $vatRate,
            'vat_amount' => $vat,
            'total' => $total,
            'status' => 'active',
            'issued_by' => $user?->id,
        ]);
    }

    public function void(TaxInvoice $taxInvoice, string $reason, ?User $user = null): TaxInvoice
    {
        if ($taxInvoice->status === 'voided') {
            throw ValidationException::withMessages(['status' => 'Already voided']);
        }
        $taxInvoice->status = 'voided';
        $taxInvoice->voided_at = now();
        $taxInvoice->void_reason = $reason;
        $taxInvoice->save();

        return $taxInvoice->fresh();
    }
}
