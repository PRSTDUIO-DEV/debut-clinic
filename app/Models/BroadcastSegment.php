<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BroadcastSegment extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'name', 'description', 'rules',
        'last_resolved_count', 'last_resolved_at',
        'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'last_resolved_count' => 'integer',
            'last_resolved_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
