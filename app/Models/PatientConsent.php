<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientConsent extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    public const STATUSES = ['pending', 'signed', 'expired'];

    protected $fillable = [
        'branch_id', 'patient_id', 'template_id',
        'name', 'file_path', 'signature_path', 'signed_by_name',
        'signed_at', 'expires_at', 'status',
        'uploaded_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class, 'template_id');
    }

    public function fileUrl(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/storage/'.ltrim($this->file_path, '/');
    }

    public function signatureUrl(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/storage/'.ltrim($this->signature_path, '/');
    }
}
