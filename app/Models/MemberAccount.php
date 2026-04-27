<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberAccount extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    public const STATUSES = ['active', 'expired', 'suspended'];

    protected $fillable = [
        'branch_id', 'patient_id', 'package_name',
        'total_deposit', 'total_used', 'balance',
        'expires_at', 'status',
        'last_topup_at', 'last_used_at', 'lifetime_topups',
    ];

    protected function casts(): array
    {
        return [
            'total_deposit' => 'decimal:2',
            'total_used' => 'decimal:2',
            'balance' => 'decimal:2',
            'expires_at' => 'date',
            'last_topup_at' => 'datetime',
            'last_used_at' => 'datetime',
            'lifetime_topups' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MemberTransaction::class);
    }
}
