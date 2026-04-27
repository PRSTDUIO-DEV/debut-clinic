<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use BelongsToBranch, HasFactory;

    public const SEVERITIES = ['info', 'warning', 'critical', 'success'];

    public const STATUSES = ['pending', 'sent', 'failed', 'read'];

    public const CHANNELS = ['in_app', 'line', 'sms', 'email'];

    public const RECIPIENT_TYPES = ['user', 'role', 'patient'];

    protected $fillable = [
        'branch_id', 'recipient_type', 'recipient_id',
        'type', 'severity', 'title', 'body',
        'channel', 'status', 'related_type', 'related_id',
        'data', 'read_at', 'sent_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('recipient_type', 'user')->where('recipient_id', $userId);
    }

    public function scopeUnread($q)
    {
        return $q->whereNull('read_at')->whereIn('status', ['pending', 'sent']);
    }
}
