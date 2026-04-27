<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'employee_no', 'position', 'department',
        'hire_date', 'termination_date',
        'bank_name', 'bank_account',
        'emergency_contact', 'emergency_phone',
        'address', 'national_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'termination_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
