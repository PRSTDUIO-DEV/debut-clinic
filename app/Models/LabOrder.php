<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabOrder extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'sent', 'completed', 'cancelled'];

    protected $fillable = [
        'branch_id', 'patient_id', 'visit_id',
        'order_no', 'ordered_at', 'ordered_by',
        'status', 'result_date', 'report_path', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'result_date' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function orderer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResultValue::class);
    }

    public function reportUrl(): ?string
    {
        if (! $this->report_path) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/storage/'.ltrim($this->report_path, '/');
    }
}
