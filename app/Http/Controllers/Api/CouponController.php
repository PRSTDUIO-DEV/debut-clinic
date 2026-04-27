<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Patient;
use App\Services\Marketing\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CouponController extends Controller
{
    public function __construct(private CouponService $coupons) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $q = Coupon::where('branch_id', $branchId);
        if ($request->filled('active')) {
            $q->where('is_active', $request->boolean('active'));
        }
        if ($request->filled('search')) {
            $q->where(fn ($w) => $w->where('code', 'like', $request->search.'%')->orWhere('name', 'like', '%'.$request->search.'%'));
        }

        return response()->json(['data' => $q->orderByDesc('id')->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:32', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_per_customer' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $branchId = (int) app('branch.id');
        $code = $data['code'] ?? strtoupper(Str::random(8));
        $coupon = Coupon::create(array_merge($data, ['branch_id' => $branchId, 'code' => $code, 'is_active' => $data['is_active'] ?? true]));

        return response()->json(['data' => $coupon], 201);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:200'],
            'prefix' => ['nullable', 'string', 'max:10'],
            'name' => ['required', 'string'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_per_customer' => ['nullable', 'integer', 'min:1'],
        ]);
        $branchId = (int) app('branch.id');
        $tpl = collect($data)->except(['count', 'prefix'])->toArray();
        $coupons = $this->coupons->generate($branchId, $tpl, (int) $data['count'], $data['prefix'] ?? null);

        return response()->json(['data' => $coupons], 201);
    }

    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'patient_id' => ['nullable', 'integer'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);
        $branchId = (int) app('branch.id');
        $patient = ! empty($data['patient_id']) ? Patient::find($data['patient_id']) : null;
        $r = $this->coupons->validate($data['code'], $patient, (float) $data['subtotal'], $branchId);

        return response()->json(['data' => ['coupon' => $r['coupon'], 'discount' => $r['discount']]]);
    }

    public function redemptions(Coupon $coupon): JsonResponse
    {
        return response()->json(['data' => $coupon->redemptions()->orderByDesc('redeemed_at')->paginate(50)]);
    }
}
