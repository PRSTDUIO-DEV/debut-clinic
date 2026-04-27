<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToBranch, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'product_id', 'warehouse_id', 'type', 'quantity',
        'before_qty', 'after_qty', 'lot_no', 'expiry_date', 'cost_price',
        'reference_type', 'reference_id', 'user_id', 'notes', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'created_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
