<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FollowUp extends Model
{
    use Auditable, BelongsToBranch, HasFactory, SoftDeletes;

    public const PRIORITIES = ['critical', 'high', 'normal', 'low'];

    public const STATUSES = ['pending', 'contacted', 'scheduled', 'completed', 'cancelled'];

    /** Allowed status transitions. */
    public const TRANSITIONS = [
        'pending' => ['contacted', 'scheduled', 'cancelled'],
        'contacted' => ['scheduled', 'cancelled', 'completed'],
        'scheduled' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'branch_id', 'patient_id', 'visit_id', 'procedure_id', 'doctor_id',
        'follow_up_date', 'priority', 'status',
        'contact_attempts', 'last_contacted_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_date' => 'date',
            'last_contacted_at' => 'datetime',
            'contact_attempts' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function canTransitionTo(string $next): bool
    {
        return in_array($next, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
