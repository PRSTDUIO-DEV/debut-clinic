<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Procedure extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'code', 'name', 'category',
        'price', 'cost', 'is_package', 'package_sessions', 'package_validity_days',
        'duration_minutes',
        'doctor_fee_rate', 'staff_commission_rate',
        'follow_up_days', 'is_active', 'position', 'color', 'icon',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'doctor_fee_rate' => 'decimal:2',
            'staff_commission_rate' => 'decimal:2',
            'duration_minutes' => 'integer',
            'follow_up_days' => 'integer',
            'is_active' => 'boolean',
            'is_package' => 'boolean',
            'package_sessions' => 'integer',
            'package_validity_days' => 'integer',
        ];
    }
}
