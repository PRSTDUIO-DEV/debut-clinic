<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Visit;
use App\Services\Marketing\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviews) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $q = Review::where('branch_id', $branchId)->with(['patient', 'doctor', 'visit']);
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('rating')) {
            $q->where('rating', (int) $request->rating);
        }

        return response()->json(['data' => $q->orderByDesc('id')->paginate(50)]);
    }

    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visit_id' => ['required', 'integer'],
            'source' => ['nullable', 'in:line,email,walk_in,website'],
        ]);
        $visit = Visit::findOrFail($data['visit_id']);
        $review = $this->reviews->requestReview($visit, $data['source'] ?? 'line');

        return response()->json(['data' => $review], 201);
    }

    public function moderate(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:published,hidden'],
        ]);
        $r = $this->reviews->moderate($review, $data['status'], $request->user());

        return response()->json(['data' => $r]);
    }

    public function aggregate(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');

        return response()->json(['data' => $this->reviews->aggregate($branchId)]);
    }
}
