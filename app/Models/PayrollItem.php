<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id', 'user_id',
        'base_pay', 'commission_total', 'overtime_pay',
        'bonus', 'deduction', 'net_pay',
        'hours_worked', 'days_worked', 'late_count',
        'compensation_type', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_pay' => 'decimal:2',
            'commission_total' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'bonus' => 'decimal:2',
            'deduction' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'hours_worked' => 'decimal:2',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
