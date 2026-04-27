<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabResultValue extends Model
{
    use HasFactory;

    public const FLAGS = ['normal', 'low', 'high', 'critical'];

    protected $fillable = [
        'lab_order_id', 'lab_test_id',
        'value_numeric', 'value_text', 'abnormal_flag',
        'notes', 'measured_at', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'value_numeric' => 'decimal:4',
            'measured_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }
}
