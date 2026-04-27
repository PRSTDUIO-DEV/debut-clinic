<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'code', 'name', 'type', 'value', 'min_amount', 'max_discount',
        'max_uses', 'used_count', 'max_per_customer', 'valid_from', 'valid_to',
        'applicable_category_id', 'applicable_procedure_id', 'is_active', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
