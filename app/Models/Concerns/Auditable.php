<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Apply this trait to any Eloquent model whose mutations must produce an
 * append-only audit log entry.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $m) {
            self::writeAudit($m, 'create', null, $m->getAttributes());
        });

        static::updated(function (Model $m) {
            $original = $m->getOriginal();
            $changes = $m->getChanges();
            $oldOnly = array_intersect_key($original, $changes);
            self::writeAudit($m, 'update', $oldOnly, $changes);
        });

        static::deleted(function (Model $m) {
            self::writeAudit($m, 'delete', $m->getOriginal(), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $m) {
                self::writeAudit($m, 'restore', null, $m->getAttributes());
            });
        }
    }

    private static function writeAudit(Model $m, string $action, ?array $old, ?array $new): void
    {
        $request = function_exists('request') ? request() : null;

        AuditLog::create([
            'user_id' => Auth::id(),
            'branch_id' => app()->bound('branch.id') ? app('branch.id') : ($m->branch_id ?? null),
            'action' => $action,
            'auditable_type' => $m::class,
            'auditable_id' => $m->getKey(),
            'old_values' => $old ? self::scrubAttributes($old) : null,
            'new_values' => $new ? self::scrubAttributes($new) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Strip sensitive fields like password/remember_token from values.
     */
    private static function scrubAttributes(array $attrs): array
    {
        unset($attrs['password'], $attrs['remember_token']);

        return $attrs;
    }
}
