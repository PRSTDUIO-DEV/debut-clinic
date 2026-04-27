<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionRate extends Model
{
    use Auditable, BelongsToBranch, HasFactory, SoftDeletes;

    public const TYPES = ['doctor_fee', 'staff_commission', 'referral'];

    public const APPLICABLE_TYPES = ['procedure', 'procedure_category', 'all'];

    protected $fillable = [
        'branch_id', 'type', 'applicable_type', 'applicable_id',
        'user_id', 'rate', 'fixed_amount', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
