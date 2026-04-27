<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class MessagingProvider extends Model
{
    use BelongsToBranch, HasFactory, SoftDeletes;

    public const TYPES = ['line', 'sms', 'email'];

    public const STATUSES = ['ok', 'warning', 'error', 'unknown'];

    protected $fillable = [
        'branch_id', 'type', 'name', 'config',
        'is_active', 'is_default', 'status',
        'last_check_at', 'last_error',
    ];

    protected $hidden = ['config'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'last_check_at' => 'datetime',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function configArray(): array
    {
        if (empty($this->config)) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($this->config), true) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function setConfigAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['config'] = Crypt::encryptString(json_encode($value));
        } else {
            $this->attributes['config'] = $value;
        }
    }
}
