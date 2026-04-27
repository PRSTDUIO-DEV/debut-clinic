<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BroadcastTemplate extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    public const CHANNELS = ['line', 'sms', 'email'];

    protected $fillable = [
        'branch_id', 'code', 'name', 'channel',
        'subject', 'body', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
