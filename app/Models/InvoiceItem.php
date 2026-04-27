<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    public const TYPES = ['procedure', 'product', 'course', 'package'];

    protected $fillable = [
        'invoice_id',
        'item_type', 'item_id', 'course_id', 'item_name',
        'quantity', 'unit_price', 'discount', 'total', 'cost_price',
        'doctor_id', 'staff_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'cost_price' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
