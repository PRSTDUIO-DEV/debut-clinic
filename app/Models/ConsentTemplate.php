<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConsentTemplate extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'code', 'title', 'body_html',
        'validity_days', 'require_signature', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'validity_days' => 'integer',
            'require_signature' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
