<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Patient extends Model
{
    use Auditable, BelongsToBranch, HasFactory, HasUuid, Searchable, SoftDeletes;

    protected $fillable = [
        'branch_id', 'hn',
        'prefix', 'first_name', 'last_name', 'nickname',
        'gender', 'date_of_birth', 'id_card',
        'phone', 'email', 'line_id', 'line_user_id', 'line_linked_at', 'address',
        'allergies', 'underlying_diseases', 'blood_type',
        'emergency_contact', 'avatar', 'source',
        'customer_group_id',
        'total_spent', 'visit_count', 'last_visit_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'last_visit_at' => 'datetime',
            'line_linked_at' => 'datetime',
            'emergency_contact' => 'array',
            'total_spent' => 'decimal:2',
            'visit_count' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function fullName(): string
    {
        return trim(($this->prefix ? $this->prefix.' ' : '').$this->first_name.' '.$this->last_name);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function memberAccount(): HasOne
    {
        return $this->hasOne(MemberAccount::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PatientPhoto::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(PatientConsent::class);
    }

    public function labOrders(): HasMany
    {
        return $this->hasMany(LabOrder::class);
    }

    /**
     * Index name per branch keeps Meilisearch tenant-aware while still global.
     */
    public function searchableAs(): string
    {
        return 'patients';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'uuid' => (string) $this->uuid,
            'branch_id' => (int) $this->branch_id,
            'hn' => (string) $this->hn,
            'first_name' => (string) $this->first_name,
            'last_name' => (string) $this->last_name,
            'nickname' => (string) ($this->nickname ?? ''),
            'phone' => (string) ($this->phone ?? ''),
            'line_id' => (string) ($this->line_id ?? ''),
            'email' => (string) ($this->email ?? ''),
        ];
    }
}
