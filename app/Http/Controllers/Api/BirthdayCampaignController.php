<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BirthdayCampaign;
use App\Services\BirthdayCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BirthdayCampaignController extends Controller
{
    public function __construct(private BirthdayCampaignService $campaigns) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = BirthdayCampaign::query()
            ->where('branch_id', $branchId)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($c) => $this->present($c)),
        ]);
    }

    public function show(BirthdayCampaign $campaign): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($campaign->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }

        return response()->json(['data' => $this->present($campaign)]);
    }

    public function store(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'templates' => ['required', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['branch_id'] = $branchId;
        $data['created_by'] = $request->user()->id;
        $data['is_active'] ??= true;

        return response()->json(['data' => $this->present(BirthdayCampaign::create($data))], 201);
    }

    public function update(Request $request, BirthdayCampaign $campaign): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($campaign->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'templates' => ['sometimes', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $campaign->fill($data)->save();

        return response()->json(['data' => $this->present($campaign->fresh())]);
    }

    public function destroy(BirthdayCampaign $campaign): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($campaign->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $campaign->delete();

        return response()->json(null, 204);
    }

    public function sendNow(Request $request, BirthdayCampaign $campaign): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($campaign->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        // Force re-run (ignore last_run_at)
        $campaign->last_run_at = null;
        $campaign->save();
        $written = $this->campaigns->runCampaign($campaign->fresh());

        return response()->json(['data' => ['written' => $written, 'campaign' => $this->present($campaign->fresh())]]);
    }

    private function present(BirthdayCampaign $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'description' => $c->description,
            'templates' => $c->templates,
            'is_active' => $c->is_active,
            'last_run_at' => optional($c->last_run_at)->toIso8601String(),
            'total_sent' => $c->total_sent,
            'created_at' => optional($c->created_at)->toIso8601String(),
        ];
    }
}
