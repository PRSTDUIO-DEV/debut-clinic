<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = ['checklist_id', 'position', 'title', 'description', 'requires_photo', 'requires_note', 'default_pass'];

    protected function casts(): array
    {
        return [
            'requires_photo' => 'boolean',
            'requires_note' => 'boolean',
            'default_pass' => 'boolean',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(QcChecklist::class, 'checklist_id');
    }
}
