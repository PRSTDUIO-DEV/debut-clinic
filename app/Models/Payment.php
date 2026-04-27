<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    public const METHODS = ['cash', 'credit_card', 'transfer', 'member_credit', 'coupon'];

    protected $fillable = [
        'branch_id', 'invoice_id',
        'method', 'amount',
        'bank_id', 'mdr_rate', 'mdr_amount',
        'reference_no', 'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'mdr_rate' => 'decimal:2',
            'mdr_amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
