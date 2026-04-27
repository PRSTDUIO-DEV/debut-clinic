<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    public const PAYMENT_METHODS = ['cash', 'transfer', 'credit_card', 'check', 'other'];

    protected $fillable = [
        'branch_id', 'category_id', 'expense_date', 'amount',
        'payment_method', 'vendor', 'description', 'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
