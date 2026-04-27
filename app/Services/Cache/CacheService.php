<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Centralised cache helper with branch-aware namespacing.
 * Use to avoid stale data across branches and to make invalidation explicit.
 */
class CacheService
{
    /** Cache TTLs in seconds. */
    public const TTL_DASHBOARD = 60;

    public const TTL_MIS = 60;

    public const TTL_DAILY_PL = 300;

    public const TTL_LOOKUP = 3600;

    public const TTL_REPORT = 180;

    /**
     * Generate a branch-scoped key.
     */
    public function key(int $branchId, string $name, array $args = []): string
    {
        $argsHash = $args ? ':'.md5(json_encode($args)) : '';

        return "b{$branchId}.{$name}{$argsHash}";
    }

    /**
     * Remember-with-tags helper. Falls back to plain cache on drivers without tags.
     */
    public function remember(int $branchId, string $name, int $ttl, callable $producer, array $args = []): mixed
    {
        $key = $this->key($branchId, $name, $args);

        try {
            return Cache::tags(['branch:'.$branchId, $name])->remember($key, $ttl, $producer);
        } catch (\BadMethodCallException $e) {
            // Driver doesn't support tags (e.g. file/database)
            return Cache::remember($key, $ttl, $producer);
        }
    }

    /**
     * Invalidate by namespace (all keys created with given $name).
     */
    public function forget(int $branchId, string $name): void
    {
        try {
            Cache::tags([$name])->flush();
        } catch (\BadMethodCallException $e) {
            // Best-effort: flush by exact key when args known is impossible; rely on TTL
        }
    }

    /**
     * Invalidate everything for a branch.
     */
    public function forgetBranch(int $branchId): void
    {
        try {
            Cache::tags(['branch:'.$branchId])->flush();
        } catch (\BadMethodCallException $e) {
        }
    }

    /**
     * Lookup data (procedures/doctors/rooms) — long TTL, invalidated on settings save.
     */
    public function lookup(int $branchId, string $name, callable $producer): array
    {
        return $this->remember($branchId, "lookup.{$name}", self::TTL_LOOKUP, $producer);
    }
}
