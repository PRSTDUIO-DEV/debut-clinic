<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['branch_id', 'name', 'type', 'room_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function scopeMain($q)
    {
        return $q->where('type', 'main');
    }

    public function scopeFloor($q)
    {
        return $q->where('type', 'floor');
    }
}
