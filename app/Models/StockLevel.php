<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'warehouse_id', 'quantity', 'lot_no',
        'expiry_date', 'cost_price', 'par_level', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'datetime',
            'quantity' => 'integer',
            'par_level' => 'integer',
            'cost_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}
