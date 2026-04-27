<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QcRun extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'checklist_id', 'branch_id', 'run_date', 'status',
        'performed_by', 'completed_at', 'total_items',
        'passed_count', 'failed_count', 'na_count', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(QcChecklist::class, 'checklist_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QcRunItem::class, 'run_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
