<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    use HasFactory;

    protected $fillable = ['pr_id', 'product_id', 'description', 'quantity', 'estimated_cost'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'estimated_cost' => 'decimal:2'];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'pr_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
