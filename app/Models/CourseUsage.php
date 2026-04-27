<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseUsage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'course_id', 'visit_id', 'session_number', 'used_at',
        'doctor_id', 'notes', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'created_at' => 'datetime',
            'session_number' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
