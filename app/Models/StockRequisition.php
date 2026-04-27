<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockRequisition extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'document_no', 'source_warehouse_id', 'dest_warehouse_id',
        'status', 'requested_by', 'approved_by', 'requested_at', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'dest_warehouse_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequisitionItem::class);
    }
}
