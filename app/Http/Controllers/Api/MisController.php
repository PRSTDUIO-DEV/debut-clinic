<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MisController extends Controller
{
    public function __construct(private MisService $mis) {}

    public function dashboard(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $period = $request->query('period', 'month');

        return response()->json(['data' => $this->mis->dashboard($branchId, $period)]);
    }

    public function charts(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $days = (int) min(365, max(1, (int) $request->query('days', 30)));

        return response()->json(['data' => $this->mis->charts($branchId, $days)]);
    }

    public function topProcedures(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $limit = (int) min(50, max(1, (int) $request->query('limit', 10)));

        return response()->json(['data' => $this->mis->topProcedures($branchId, $from, $to, $limit)]);
    }

    public function topDoctors(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $limit = (int) min(50, max(1, (int) $request->query('limit', 10)));

        return response()->json(['data' => $this->mis->topDoctors($branchId, $from, $to, $limit)]);
    }

    public function topCustomerGroups(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return response()->json(['data' => $this->mis->topCustomerGroups($branchId, $from, $to)]);
    }

    public function topPatients(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $limit = (int) min(50, max(1, (int) $request->query('limit', 10)));

        return response()->json(['data' => $this->mis->topPatients($branchId, $from, $to, $limit)]);
    }
}
