<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagingLog extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'sent', 'failed', 'bounced'];

    protected $fillable = [
        'provider_id', 'branch_id', 'channel',
        'recipient_address', 'payload', 'response',
        'status', 'external_id', 'error',
        'related_type', 'related_id', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(MessagingProvider::class, 'provider_id');
    }
}
