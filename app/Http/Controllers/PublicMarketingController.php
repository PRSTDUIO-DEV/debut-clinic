<?php

namespace App\Http\Controllers;

use App\Models\InfluencerCampaign;
use App\Models\Review;
use App\Services\Marketing\InfluencerService;
use App\Services\Marketing\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicMarketingController extends Controller
{
    public function __construct(
        private InfluencerService $influencer,
        private ReviewService $reviews,
    ) {}

    /**
     * /r/{shortcode} → log click → set referral cookie → redirect to landing_url with UTM params.
     */
    public function utmLand(string $shortcode, Request $request): RedirectResponse
    {
        $campaign = InfluencerCampaign::where('shortcode', $shortcode)->first();
        if (! $campaign || $campaign->status !== 'active') {
            return redirect('/');
        }

        $referral = $this->influencer->trackClick(
            $campaign,
            $request->ip(),
            $request->userAgent(),
        );

        $url = $campaign->landing_url ?: '/';
        $glue = str_contains($url, '?') ? '&' : '?';
        $params = http_build_query([
            'utm_source' => $campaign->utm_source,
            'utm_medium' => $campaign->utm_medium,
            'utm_campaign' => $campaign->utm_campaign,
        ]);

        return redirect($url.$glue.$params)
            ->withCookie(cookie('debut_referral_id', (string) $referral->id, 60 * 24 * 30));
    }

    /**
     * Public review submission (signed URL by token).
     */
    public function submitReview(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $r = $this->reviews->submit($token, (int) $data['rating'], $data['title'] ?? null, $data['body'] ?? null);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(['data' => ['id' => $r->id, 'status' => $r->status]], 201);
    }

    public function showReviewForm(string $token)
    {
        $review = Review::where('public_token', $token)->first();
        if (! $review) {
            return response()->view('public.review-not-found', [], 404);
        }
        if ($review->submitted_at) {
            return view('public.review-thanks', ['review' => $review]);
        }

        return view('public.review-form', ['token' => $token, 'review' => $review]);
    }
}
