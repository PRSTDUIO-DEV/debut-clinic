<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberTransaction extends Model
{
    use HasFactory;

    public $timestamps = false;

    public const TYPES = ['deposit', 'usage', 'refund', 'adjustment'];

    protected $fillable = [
        'member_account_id', 'type', 'amount',
        'balance_before', 'balance_after',
        'invoice_id', 'notes', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function memberAccount(): BelongsTo
    {
        return $this->belongsTo(MemberAccount::class);
    }
}
