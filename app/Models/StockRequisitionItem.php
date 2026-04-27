<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequisitionItem extends Model
{
    use HasFactory;

    protected $fillable = ['stock_requisition_id', 'product_id', 'requested_qty', 'approved_qty'];

    protected function casts(): array
    {
        return ['requested_qty' => 'integer', 'approved_qty' => 'integer'];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(StockRequisition::class, 'stock_requisition_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
