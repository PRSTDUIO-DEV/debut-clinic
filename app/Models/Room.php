<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'name', 'type', 'floor', 'is_active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'floor' => 'integer',
        ];
    }
}
