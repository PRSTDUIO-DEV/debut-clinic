<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommandRun extends Model
{
    use HasFactory;

    protected $fillable = ['command', 'started_at', 'finished_at', 'duration_ms', 'exit_code', 'output', 'branch_id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
