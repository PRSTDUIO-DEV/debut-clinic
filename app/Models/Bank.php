<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'name', 'account_no', 'mdr_rate', 'is_active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'mdr_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
