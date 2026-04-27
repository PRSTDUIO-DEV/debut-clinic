<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Services\Cache\CacheService;
use App\Services\MisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthAndCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_without_auth(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_ready_endpoint_runs_full_checks(): void
    {
        $res = $this->getJson('/ready');
        // status code 200 ok or 503 degraded — both valid responses
        $this->assertContains($res->status(), [200, 503]);
        $this->assertNotNull($res->json('checks.database.status'));
        $this->assertNotNull($res->json('checks.cache.status'));
        $this->assertNotNull($res->json('checks.storage.status'));
    }

    public function test_cache_service_remembers_with_branch_namespace(): void
    {
        $svc = app(CacheService::class);
        $count = 0;
        $producer = function () use (&$count) {
            $count++;

            return ['x' => $count];
        };

        $r1 = $svc->remember(1, 'test.key', 60, $producer);
        $r2 = $svc->remember(1, 'test.key', 60, $producer);
        $r3 = $svc->remember(2, 'test.key', 60, $producer); // different branch

        $this->assertSame(1, $r1['x']);
        $this->assertSame(1, $r2['x']); // cached
        $this->assertSame(2, $r3['x']); // separate cache key
    }

    public function test_cache_key_changes_with_args(): void
    {
        $svc = app(CacheService::class);
        $count = 0;
        $producer = function () use (&$count) {
            $count++;

            return $count;
        };

        $a = $svc->remember(1, 'k', 60, $producer, ['period' => 'month']);
        $b = $svc->remember(1, 'k', 60, $producer, ['period' => 'year']);
        $a2 = $svc->remember(1, 'k', 60, $producer, ['period' => 'month']);

        $this->assertSame(1, $a);
        $this->assertSame(2, $b);
        $this->assertSame(1, $a2); // hits cache
    }

    public function test_mis_dashboard_uses_cache(): void
    {
        $branch = Branch::factory()->create();
        Cache::flush();

        $r1 = app(MisService::class)->dashboard($branch->id, 'month');
        $this->assertArrayHasKey('kpis', $r1);

        // Second call should hit cache (not throw, return same shape)
        $r2 = app(MisService::class)->dashboard($branch->id, 'month');
        $this->assertSame($r1, $r2);
    }

    public function test_command_palette_pages_route_exists(): void
    {
        // Smoke test: critical pages are registered
        $routes = collect(\Route::getRoutes())->map(fn ($r) => $r->uri())->all();
        foreach (['dashboard', 'patients', 'appointments', 'pos', 'reports', 'mis', 'admin/staff', 'admin/payroll', 'admin/system-health'] as $path) {
            $this->assertContains($path, $routes, "Route $path should exist");
        }
    }
}
