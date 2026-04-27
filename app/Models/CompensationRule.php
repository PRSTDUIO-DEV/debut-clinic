<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompensationRule extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'user_id', 'role_id',
        'type', 'base_amount', 'commission_rate',
        'applicable_procedure_id',
        'valid_from', 'valid_to', 'is_active', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
