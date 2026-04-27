<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Disbursement extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    public const TYPES = ['salary', 'utilities', 'rent', 'tax', 'supplier', 'other'];

    public const STATUSES = ['draft', 'approved', 'paid', 'cancelled'];

    public const PAYMENT_METHODS = ['cash', 'transfer', 'check', 'credit_card'];

    protected $fillable = [
        'branch_id', 'disbursement_no', 'disbursement_date',
        'type', 'amount', 'payment_method',
        'vendor', 'reference', 'related_po_id',
        'description', 'status',
        'requested_by', 'approved_by',
        'approved_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'disbursement_date' => 'date',
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function relatedPo(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'related_po_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
