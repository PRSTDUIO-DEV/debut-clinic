<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\Marketing\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(private PromotionService $promotions) {}

    public function index(): JsonResponse
    {
        $branchId = (int) app('branch.id');

        return response()->json(['data' => Promotion::where('branch_id', $branchId)->orderByDesc('id')->paginate(50)]);
    }

    public function active(): JsonResponse
    {
        $branchId = (int) app('branch.id');

        return response()->json(['data' => $this->promotions->applicableNow($branchId)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:percent,fixed,buy_x_get_y,bundle'],
            'rules' => ['required', 'array'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer'],
        ]);
        $branchId = (int) app('branch.id');
        $promo = Promotion::create(array_merge($data, ['branch_id' => $branchId]));

        return response()->json(['data' => $promo], 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:percent,fixed,buy_x_get_y,bundle'],
            'rules' => ['sometimes', 'array'],
            'valid_from' => ['sometimes', 'date'],
            'valid_to' => ['sometimes', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer'],
        ]);
        $promotion->fill($data)->save();

        return response()->json(['data' => $promotion->fresh()]);
    }

    public function preview(Request $request, Promotion $promotion): JsonResponse
    {
        $data = $request->validate([
            'subtotal' => ['required', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
        ]);
        $r = $this->promotions->applyToCart($promotion, $data);

        return response()->json(['data' => $r]);
    }
}
