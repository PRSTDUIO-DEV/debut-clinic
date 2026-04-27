<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosing extends Model
{
    use BelongsToBranch, HasFactory;

    public const STATUSES = ['draft', 'closed', 'reopened'];

    protected $fillable = [
        'branch_id', 'closing_date',
        'expected_cash', 'counted_cash', 'variance',
        'total_revenue', 'total_cogs', 'total_commission',
        'total_mdr', 'total_expenses',
        'gross_profit', 'net_profit',
        'payment_breakdown', 'status',
        'closed_by', 'closed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'total_revenue' => 'decimal:2',
            'total_cogs' => 'decimal:2',
            'total_commission' => 'decimal:2',
            'total_mdr' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'payment_breakdown' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
