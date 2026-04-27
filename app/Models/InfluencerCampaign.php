<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InfluencerCampaign extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'influencer_id', 'branch_id', 'name', 'shortcode',
        'utm_source', 'utm_medium', 'utm_campaign', 'landing_url',
        'start_date', 'end_date', 'total_budget', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_budget' => 'decimal:2',
        ];
    }

    public function influencer(): BelongsTo
    {
        return $this->belongsTo(Influencer::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(InfluencerReferral::class, 'campaign_id');
    }
}
