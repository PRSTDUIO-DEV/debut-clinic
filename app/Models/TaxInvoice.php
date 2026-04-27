<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxInvoice extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'voided'];

    protected $fillable = [
        'branch_id', 'invoice_id', 'tax_invoice_no', 'issued_at',
        'customer_name', 'customer_tax_id', 'customer_address',
        'taxable_amount', 'vat_rate', 'vat_amount', 'total',
        'status', 'issued_by',
        'voided_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'voided_at' => 'datetime',
            'taxable_amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
