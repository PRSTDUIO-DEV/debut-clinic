<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceivingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receiving_id', 'product_id', 'quantity',
        'unit_cost', 'total', 'lot_no', 'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'unit_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function receiving(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiving::class, 'goods_receiving_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
