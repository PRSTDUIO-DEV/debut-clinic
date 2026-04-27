<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientPhoto extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'patient_id', 'visit_id',
        'type', 'file_path', 'thumbnail_path',
        'width', 'height', 'mime_type', 'file_size', 'storage_disk',
        'taken_at', 'uploaded_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function url(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/storage/'.ltrim($this->file_path, '/');
    }

    public function thumbnailUrl(): ?string
    {
        if (! $this->thumbnail_path) {
            return $this->url();
        }

        return rtrim(config('app.url'), '/').'/storage/'.ltrim($this->thumbnail_path, '/');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
