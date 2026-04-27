<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Influencer;
use App\Models\InfluencerCampaign;
use App\Models\InfluencerReferral;
use App\Services\Marketing\InfluencerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfluencerController extends Controller
{
    public function __construct(private InfluencerService $influencer) {}

    public function index(): JsonResponse
    {
        $branchId = (int) app('branch.id');

        return response()->json(['data' => Influencer::where('branch_id', $branchId)->orderByDesc('id')->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:instagram,facebook,tiktok,youtube,line,other'],
            'handle' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $branchId = (int) app('branch.id');

        return response()->json(['data' => Influencer::create(array_merge($data, ['branch_id' => $branchId]))], 201);
    }

    public function update(Request $request, Influencer $influencer): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'channel' => ['sometimes', 'in:instagram,facebook,tiktok,youtube,line,other'],
            'handle' => ['nullable', 'string'],
            'contact' => ['nullable', 'string'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $influencer->fill($data)->save();

        return response()->json(['data' => $influencer->fresh()]);
    }

    public function campaigns(Influencer $influencer): JsonResponse
    {
        return response()->json(['data' => $influencer->campaigns()->orderByDesc('id')->paginate(50)]);
    }

    public function storeCampaign(Request $request, Influencer $influencer): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'utm_source' => ['required', 'string'],
            'utm_medium' => ['nullable', 'string'],
            'utm_campaign' => ['required', 'string'],
            'landing_url' => ['nullable', 'url'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:draft,active,paused,ended'],
        ]);
        $branchId = (int) app('branch.id');
        $campaign = InfluencerCampaign::create(array_merge($data, [
            'influencer_id' => $influencer->id,
            'branch_id' => $branchId,
            'shortcode' => $this->influencer->generateShortcode(),
            'status' => $data['status'] ?? 'draft',
        ]));

        return response()->json(['data' => $campaign], 201);
    }

    public function campaignReport(InfluencerCampaign $campaign): JsonResponse
    {
        $this->influencer->recomputeLtv($campaign);

        return response()->json(['data' => $this->influencer->report($campaign->fresh())]);
    }

    public function referrals(Request $request): JsonResponse
    {
        $q = InfluencerReferral::query()
            ->with(['campaign', 'patient']);
        if ($request->filled('campaign_id')) {
            $q->where('campaign_id', (int) $request->campaign_id);
        }

        return response()->json(['data' => $q->orderByDesc('id')->paginate(50)]);
    }
}
