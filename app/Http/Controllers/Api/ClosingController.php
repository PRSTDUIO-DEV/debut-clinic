<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyClosing;
use App\Services\ClosingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClosingController extends Controller
{
    public function __construct(private ClosingService $closings) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = DailyClosing::query()
            ->where('branch_id', $branchId)
            ->with('closer:id,name')
            ->orderByDesc('closing_date')
            ->limit(60)
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($c) => $this->present($c)),
        ]);
    }

    public function show(DailyClosing $closing): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($closing->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }

        return response()->json(['data' => $this->present($closing->load('closer:id,name'))]);
    }

    public function prepare(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate(['date' => ['nullable', 'date']]);
        $closing = $this->closings->prepare($branchId, $data['date'] ?? null);

        return response()->json(['data' => $this->present($closing->load('closer:id,name'))]);
    }

    public function commit(Request $request, DailyClosing $closing): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($closing->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->closings->commit($closing, (float) $data['counted_cash'], $request->user(), $data['notes'] ?? null);

        return response()->json(['data' => $this->present($closing->fresh()->load('closer:id,name'))]);
    }

    public function reopen(Request $request, DailyClosing $closing): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($closing->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->closings->reopen($closing, $data['reason'], $request->user());

        return response()->json(['data' => $this->present($closing->fresh()->load('closer:id,name'))]);
    }

    private function present(DailyClosing $c): array
    {
        return [
            'id' => $c->id,
            'closing_date' => $c->closing_date?->toDateString(),
            'status' => $c->status,
            'expected_cash' => (float) $c->expected_cash,
            'counted_cash' => (float) $c->counted_cash,
            'variance' => (float) $c->variance,
            'total_revenue' => (float) $c->total_revenue,
            'total_cogs' => (float) $c->total_cogs,
            'total_commission' => (float) $c->total_commission,
            'total_mdr' => (float) $c->total_mdr,
            'total_expenses' => (float) $c->total_expenses,
            'gross_profit' => (float) $c->gross_profit,
            'net_profit' => (float) $c->net_profit,
            'payment_breakdown' => $c->payment_breakdown,
            'closed_by' => $c->closer?->name,
            'closed_at' => optional($c->closed_at)->toIso8601String(),
            'notes' => $c->notes,
        ];
    }
}
