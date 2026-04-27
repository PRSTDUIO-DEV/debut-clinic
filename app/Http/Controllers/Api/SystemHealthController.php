<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommandRun;
use App\Services\Admin\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
    public function __construct(private SystemHealthService $health) {}

    public function snapshot(): JsonResponse
    {
        return response()->json(['data' => $this->health->snapshot()]);
    }

    public function cronHistory(Request $request): JsonResponse
    {
        $q = CommandRun::query();
        if ($request->filled('command')) {
            $q->where('command', $request->command);
        }

        return response()->json(['data' => $q->orderByDesc('started_at')->limit(200)->get()]);
    }

    public function retryFailedJob(Request $request, int $jobId): JsonResponse
    {
        if (! \Schema::hasTable('failed_jobs')) {
            return response()->json(['message' => 'failed_jobs table missing'], 404);
        }
        $row = DB::table('failed_jobs')->where('id', $jobId)->first();
        if (! $row) {
            return response()->json(['message' => 'job not found'], 404);
        }

        try {
            \Artisan::call('queue:retry', ['id' => [(string) $jobId]]);

            return response()->json(['data' => ['retried' => $jobId, 'output' => \Artisan::output()]]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'retry failed: '.$e->getMessage()], 500);
        }
    }
}
