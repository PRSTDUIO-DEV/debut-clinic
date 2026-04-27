<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcRunItem extends Model
{
    use HasFactory;

    protected $fillable = ['run_id', 'item_id', 'status', 'note', 'photo_path', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(QcRun::class, 'run_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(QcChecklistItem::class, 'item_id');
    }
}
