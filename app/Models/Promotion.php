<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['branch_id', 'name', 'type', 'rules', 'valid_from', 'valid_to', 'is_active', 'priority'];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
