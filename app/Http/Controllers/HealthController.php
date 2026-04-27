<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Public liveness/readiness endpoints for load balancers + uptime monitors.
 *
 * /health  — quick liveness (always 200 if app boots)
 * /ready   — full readiness: DB + cache + storage; returns 503 on any failure
 */
class HealthController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => config('app.name'),
            'time' => now()->toIso8601String(),
            'uptime_pid' => getmypid(),
        ]);
    }

    public function ready(): JsonResponse
    {
        $checks = [];

        // DB
        try {
            $start = microtime(true);
            DB::selectOne('SELECT 1 as ok');
            $checks['database'] = ['status' => 'ok', 'latency_ms' => round((microtime(true) - $start) * 1000, 2)];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'fail', 'error' => $e->getMessage()];
        }

        // Cache
        try {
            $key = '__health.'.now()->timestamp;
            Cache::put($key, '1', 5);
            $ok = Cache::get($key) === '1';
            Cache::forget($key);
            $checks['cache'] = ['status' => $ok ? 'ok' : 'fail', 'driver' => config('cache.default')];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'fail', 'error' => $e->getMessage()];
        }

        // Storage write
        try {
            $f = storage_path('framework/__health.txt');
            file_put_contents($f, (string) time());
            $checks['storage'] = ['status' => is_readable($f) ? 'ok' : 'fail'];
            @unlink($f);
        } catch (\Throwable $e) {
            $checks['storage'] = ['status' => 'fail', 'error' => $e->getMessage()];
        }

        $allOk = collect($checks)->every(fn ($c) => $c['status'] === 'ok');

        return response()->json([
            'status' => $allOk ? 'ok' : 'degraded',
            'checks' => $checks,
            'time' => now()->toIso8601String(),
        ], $allOk ? 200 : 503);
    }
}
