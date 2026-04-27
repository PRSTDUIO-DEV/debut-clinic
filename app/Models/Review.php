<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'patient_id', 'visit_id', 'doctor_id',
        'rating', 'title', 'body', 'source', 'status',
        'public_token', 'requested_at', 'submitted_at',
        'moderated_by', 'moderated_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'requested_at' => 'datetime',
            'submitted_at' => 'datetime',
            'moderated_at' => 'datetime',
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

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
