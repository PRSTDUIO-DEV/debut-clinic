<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'sent', 'partial_received', 'received', 'cancelled'];

    protected $fillable = [
        'branch_id', 'pr_id', 'supplier_id', 'po_number',
        'order_date', 'expected_date', 'status',
        'subtotal', 'vat_amount', 'total',
        'approved_by', 'sent_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'pr_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'po_id');
    }
}
