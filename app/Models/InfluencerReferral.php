<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfluencerReferral extends Model
{
    use HasFactory;

    protected $fillable = ['campaign_id', 'patient_id', 'ip', 'user_agent', 'referred_at', 'first_visit_at', 'lifetime_value'];

    protected function casts(): array
    {
        return [
            'referred_at' => 'datetime',
            'first_visit_at' => 'datetime',
            'lifetime_value' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(InfluencerCampaign::class, 'campaign_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
