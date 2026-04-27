<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use Auditable, BelongsToBranch, HasFactory, HasUuid, SoftDeletes;

    public const STATUSES = ['draft', 'paid', 'partial', 'voided', 'refunded'];

    protected $fillable = [
        'branch_id', 'visit_id', 'patient_id',
        'invoice_number', 'invoice_date',
        'subtotal', 'discount_amount', 'coupon_discount', 'promotion_discount',
        'vat_amount', 'total_amount',
        'total_cogs', 'total_commission', 'gross_profit',
        'status', 'cashier_id', 'notes',
        'coupon_id', 'promotion_id', 'referral_id',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_cogs' => 'decimal:2',
            'total_commission' => 'decimal:2',
            'gross_profit' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
