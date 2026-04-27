<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use Auditable, BelongsToBranch, HasFactory, HasUuid, SoftDeletes;

    public const STATUSES = ['pending', 'confirmed', 'arrived', 'completed', 'cancelled', 'no_show'];

    /**
     * Allowed transitions per state machine.
     *
     * @var array<string, array<int,string>>
     */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'arrived', 'cancelled', 'no_show'],
        'confirmed' => ['arrived', 'cancelled', 'no_show'],
        'arrived' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    protected $fillable = [
        'branch_id', 'patient_id', 'doctor_id', 'room_id', 'procedure_id',
        'appointment_date', 'start_time', 'end_time',
        'status', 'source', 'follow_up_id',
        'reminder_sent', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'reminder_sent' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $next): bool
    {
        return in_array($next, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
