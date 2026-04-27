<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'period_year', 'period_month', 'status',
        'total_amount', 'finalized_at', 'finalized_by',
        'paid_at', 'payment_method', 'payment_reference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'finalized_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
