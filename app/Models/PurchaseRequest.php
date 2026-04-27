<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'converted'];

    protected $fillable = [
        'branch_id', 'pr_number', 'request_date',
        'requested_by', 'approved_by',
        'submitted_at', 'approved_at',
        'status', 'estimated_total', 'notes', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'estimated_total' => 'decimal:2',
        ];
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
        return $this->hasMany(PurchaseRequestItem::class, 'pr_id');
    }
}
