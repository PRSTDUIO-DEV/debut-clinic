<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastMessage extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'sent', 'failed', 'skipped'];

    protected $fillable = [
        'campaign_id', 'patient_id', 'channel',
        'recipient_address', 'status', 'payload', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BroadcastCampaign::class, 'campaign_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
