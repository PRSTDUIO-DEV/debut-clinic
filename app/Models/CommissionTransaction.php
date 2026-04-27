<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionTransaction extends Model
{
    use BelongsToBranch, HasFactory;

    public $timestamps = false;

    public const TYPES = ['doctor_fee', 'staff_commission', 'referral'];

    protected $fillable = [
        'branch_id', 'invoice_item_id', 'user_id', 'type',
        'base_amount', 'rate', 'amount',
        'commission_date', 'is_paid', 'paid_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'commission_date' => 'date',
            'is_paid' => 'boolean',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
