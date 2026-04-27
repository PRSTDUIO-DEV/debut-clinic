<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FollowUpRule extends Model
{
    use BelongsToBranch, HasFactory;

    public const PRIORITIES = ['critical', 'high', 'normal', 'low'];

    public const CONDITIONS = [
        'overdue_days', 'course_expiring_days', 'wallet_low_amount',
        'dormant_days', 'has_critical_tag', 'vip_overdue_days',
    ];

    protected $fillable = [
        'branch_id', 'name', 'priority',
        'condition_type', 'condition_value',
        'notify_doctor', 'notify_branch_admin',
        'preferred_channel', 'is_active', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'condition_value' => 'array',
            'notify_doctor' => 'boolean',
            'notify_branch_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }
}
