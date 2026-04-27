<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['po_id', 'product_id', 'description', 'quantity', 'received_qty', 'unit_cost', 'total'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'received_qty' => 'integer',
            'unit_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
