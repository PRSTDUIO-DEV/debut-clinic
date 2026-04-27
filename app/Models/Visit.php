<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visit extends Model
{
    use Auditable, BelongsToBranch, HasFactory, HasUuid, SoftDeletes;

    public const STATUSES = ['waiting', 'in_progress', 'completed', 'cancelled'];

    protected $fillable = [
        'branch_id', 'patient_id', 'doctor_id', 'room_id', 'appointment_id',
        'visit_number', 'visit_date', 'check_in_at', 'check_out_at',
        'status', 'source', 'vital_signs',
        'chief_complaint', 'doctor_notes', 'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'vital_signs' => 'array',
            'total_amount' => 'decimal:2',
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

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }
}
