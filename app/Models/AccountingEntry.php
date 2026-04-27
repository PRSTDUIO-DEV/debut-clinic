<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingEntry extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'entry_date', 'journal_no',
        'document_type', 'document_id',
        'debit_account_id', 'credit_account_id', 'amount',
        'description', 'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }
}
