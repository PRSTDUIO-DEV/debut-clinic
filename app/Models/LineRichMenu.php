<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LineRichMenu extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = ['branch_id', 'provider_id', 'name', 'layout', 'buttons', 'image_path', 'line_rich_menu_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'buttons' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
