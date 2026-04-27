<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerGroup extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'name', 'discount_rate', 'description', 'is_active', 'position', 'color', 'icon',
    ];

    protected function casts(): array
    {
        return [
            'discount_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
