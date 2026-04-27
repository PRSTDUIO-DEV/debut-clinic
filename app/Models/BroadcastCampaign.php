<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BroadcastCampaign extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'scheduled', 'sending', 'completed', 'failed', 'cancelled'];

    protected $fillable = [
        'branch_id', 'segment_id', 'template_id', 'name',
        'scheduled_at', 'started_at', 'completed_at', 'status',
        'total_recipients', 'sent_count', 'failed_count', 'skipped_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(BroadcastSegment::class, 'segment_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BroadcastTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BroadcastMessage::class, 'campaign_id');
    }
}
