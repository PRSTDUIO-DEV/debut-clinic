<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'category_id', 'sku', 'name', 'unit',
        'selling_price', 'cost_price', 'min_stock', 'max_stock',
        'reorder_point', 'is_active', 'block_dispensing_when_expired',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'min_stock' => 'integer',
            'max_stock' => 'integer',
            'reorder_point' => 'integer',
            'is_active' => 'boolean',
            'block_dispensing_when_expired' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
