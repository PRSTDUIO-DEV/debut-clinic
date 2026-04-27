<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToBranch, HasApiTokens, HasFactory, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'employee_code',
        'name',
        'email',
        'password',
        'pin_hash',
        'phone',
        'avatar',
        'position',
        'is_doctor',
        'license_no',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'pin_hash',
        'remember_token',
    ];

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function timeClocks(): HasMany
    {
        return $this->hasMany(TimeClock::class);
    }

    public function compensationRules(): HasMany
    {
        return $this->hasMany(CompensationRule::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_doctor' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot('is_primary')->withTimestamps();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasRole(string $name): bool
    {
        return $this->roles()->where('name', $name)->exists();
    }

    public function hasPermission(string $name): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('name', $name))
            ->exists();
    }

    public function permissionNames(): array
    {
        return $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }
}
