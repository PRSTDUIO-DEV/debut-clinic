<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Influencer extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['branch_id', 'name', 'channel', 'handle', 'contact', 'commission_rate', 'is_active', 'meta'];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(InfluencerCampaign::class);
    }
}
