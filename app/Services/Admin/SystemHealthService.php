<?php

namespace App\Services\Admin;

use App\Models\CommandRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SystemHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'app' => [
                'env' => config('app.env'),
                'debug' => (bool) config('app.debug'),
                'name' => config('app.name'),
                'php_version' => PHP_VERSION,
                'laravel' => app()->version(),
                'time' => now()->toIso8601String(),
            ],
            'database' => $this->databaseStatus(),
            'cache' => $this->cacheStatus(),
            'queue' => $this->queueStatus(),
            'storage' => $this->storageStatus(),
            'cron' => $this->cronStatus(),
            'recent_errors' => $this->recentLogErrors(50),
        ];
    }

    private function databaseStatus(): array
    {
        try {
            $start = microtime(true);
            $version = DB::selectOne('SELECT VERSION() as v')?->v ?? '?';
            $tables = [];
            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql') {
                $rows = DB::select('SELECT TABLE_NAME as name, TABLE_ROWS as rows, DATA_LENGTH + INDEX_LENGTH as size FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY size DESC LIMIT 30');
                foreach ($rows as $r) {
                    $tables[] = [
                        'name' => $r->name,
                        'rows' => (int) ($r->rows ?? 0),
                        'size_bytes' => (int) ($r->size ?? 0),
                    ];
                }
            }
            $latencyMs = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'driver' => $driver,
                'version' => $version,
                'latency_ms' => $latencyMs,
                'top_tables' => $tables,
                'total_tables' => count(DB::select('SHOW TABLES')),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    private function cacheStatus(): array
    {
        try {
            $key = 'health.ping.'.now()->timestamp;
            Cache::put($key, '1', 5);
            $ok = Cache::get($key) === '1';
            Cache::forget($key);

            return [
                'status' => $ok ? 'ok' : 'fail',
                'driver' => config('cache.default'),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'driver' => config('cache.default'), 'error' => $e->getMessage()];
        }
    }

    private function queueStatus(): array
    {
        $driver = config('queue.default');
        $info = ['driver' => $driver];

        try {
            if (\Schema::hasTable('jobs')) {
                $info['pending'] = (int) DB::table('jobs')->count();
            }
            if (\Schema::hasTable('failed_jobs')) {
                $info['failed'] = (int) DB::table('failed_jobs')->count();
                $latest = DB::table('failed_jobs')->orderByDesc('failed_at')->first();
                if ($latest) {
                    $info['latest_failure'] = [
                        'queue' => $latest->queue ?? null,
                        'connection' => $latest->connection ?? null,
                        'failed_at' => $latest->failed_at,
                        'exception_first_line' => substr((string) ($latest->exception ?? ''), 0, 300),
                    ];
                }
            }
            $info['status'] = 'ok';
        } catch (\Throwable $e) {
            $info['status'] = 'error';
            $info['error'] = $e->getMessage();
        }

        return $info;
    }

    private function storageStatus(): array
    {
        $disks = ['local', 'public'];
        $out = [];
        foreach ($disks as $disk) {
            try {
                $path = Storage::disk($disk)->path('');
                if (! is_dir($path)) {
                    $out[$disk] = ['status' => 'missing'];

                    continue;
                }
                $size = $this->dirSize($path);
                $out[$disk] = [
                    'status' => 'ok',
                    'path' => $path,
                    'size_bytes' => $size,
                    'free_bytes' => @disk_free_space($path) ?: null,
                ];
            } catch (\Throwable $e) {
                $out[$disk] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return $out;
    }

    private function dirSize(string $dir): int
    {
        $size = 0;

        try {
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iter as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable $e) {
        }

        return $size;
    }

    public function cronStatus(): array
    {
        $commands = [
            'inventory:expiry-scan',
            'courses:expire',
            'crm:dispatch-due',
            'closing:auto-prepare',
            'birthday:dispatch',
            'follow-up:scan-urgent',
        ];
        $out = [];
        foreach ($commands as $c) {
            $last = CommandRun::where('command', $c)->orderByDesc('started_at')->first();
            $out[] = [
                'command' => $c,
                'last_run_at' => $last?->started_at,
                'last_duration_ms' => $last?->duration_ms,
                'last_exit_code' => $last?->exit_code,
                'last_status' => $last ? ($last->exit_code === 0 ? 'success' : 'failed') : 'never_run',
            ];
        }

        return $out;
    }

    public function recentLogErrors(int $limit = 50): array
    {
        $path = storage_path('logs/laravel.log');
        if (! is_readable($path)) {
            return [];
        }

        try {
            $lines = $this->tail($path, 2000);
            $matches = [];
            $current = null;
            foreach ($lines as $line) {
                if (preg_match('/^\[([^\]]+)\] [^\.]+\.(ERROR|CRITICAL|EMERGENCY): (.+)/', $line, $m)) {
                    $current = ['time' => $m[1], 'level' => $m[2], 'message' => substr($m[3], 0, 300)];
                    $matches[] = $current;
                    if (count($matches) >= $limit) {
                        break;
                    }
                }
            }

            return array_reverse($matches);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function tail(string $path, int $lines): array
    {
        $f = @fopen($path, 'r');
        if (! $f) {
            return [];
        }
        $buf = '';
        $size = filesize($path);
        $pos = $size;
        $chunk = 4096;
        $lineCount = 0;
        while ($pos > 0 && $lineCount < $lines) {
            $read = min($chunk, $pos);
            $pos -= $read;
            fseek($f, $pos);
            $buf = fread($f, $read).$buf;
            $lineCount = substr_count($buf, "\n");
        }
        fclose($f);
        $arr = explode("\n", $buf);

        return array_slice($arr, -$lines);
    }

    public function logCommand(string $command, callable $body): array
    {
        $log = CommandRun::create([
            'command' => $command,
            'started_at' => now(),
        ]);
        $start = microtime(true);
        $exit = 0;
        $output = '';

        try {
            $output = (string) $body();
        } catch (\Throwable $e) {
            $exit = 1;
            $output = 'EXCEPTION: '.$e->getMessage();
            Log::error('Command failed: '.$command.': '.$e->getMessage());
        }
        $log->finished_at = now();
        $log->duration_ms = (int) round((microtime(true) - $start) * 1000);
        $log->exit_code = $exit;
        $log->output = mb_substr($output, 0, 2000);
        $log->save();

        return ['exit' => $exit, 'duration_ms' => $log->duration_ms, 'output' => $output];
    }
}
