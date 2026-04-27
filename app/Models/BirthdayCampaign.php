<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BirthdayCampaign extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'name', 'description', 'templates',
        'is_active', 'last_run_at', 'total_sent', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'templates' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'total_sent' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
