<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QcChecklist extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['branch_id', 'name', 'description', 'frequency', 'applicable_role', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QcChecklistItem::class, 'checklist_id')->orderBy('position');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(QcRun::class, 'checklist_id');
    }
}
