<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTest extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'code', 'name', 'category',
        'unit', 'ref_min', 'ref_max', 'ref_text',
        'price', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ref_min' => 'decimal:4',
            'ref_max' => 'decimal:4',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
