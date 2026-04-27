<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['branch_id', 'name', 'is_active', 'position', 'color', 'icon'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
