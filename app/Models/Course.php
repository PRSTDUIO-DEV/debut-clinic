<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use Auditable, BelongsToBranch, HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'expired', 'completed', 'cancelled'];

    protected $fillable = [
        'branch_id', 'patient_id', 'name',
        'total_sessions', 'used_sessions', 'remaining_sessions',
        'expires_at', 'status', 'source_invoice_item_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'total_sessions' => 'integer',
            'used_sessions' => 'integer',
            'remaining_sessions' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CourseUsage::class);
    }
}
