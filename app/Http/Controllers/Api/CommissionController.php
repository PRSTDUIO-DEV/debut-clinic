<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionRate;
use App\Models\CommissionTransaction;
use App\Models\User;
use App\Models\Visit;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommissionController extends Controller
{
    public function __construct(private CommissionService $commissions) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $query = CommissionTransaction::query()
            ->where('branch_id', $branchId)
            ->with(['user:id,uuid,name', 'invoiceItem:id,invoice_id,item_name'])
            ->orderByDesc('commission_date')
            ->orderByDesc('id');

        if ($u = $request->query('filter.user_id')) {
            $query->where('user_id', $u);
        }
        if ($t = $request->query('filter.type')) {
            $query->where('type', $t);
        }
        if ($from = $request->query('date_from')) {
            $query->whereDate('commission_date', '>=', $from);
        }
        if ($to = $request->query('date_to')) {
            $query->whereDate('commission_date', '<=', $to);
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn ($t) => [
                'id' => $t->id,
                'user' => $t->user ? ['id' => $t->user->uuid, 'name' => $t->user->name] : null,
                'type' => $t->type,
                'base_amount' => (float) $t->base_amount,
                'rate' => $t->rate !== null ? (float) $t->rate : null,
                'amount' => (float) $t->amount,
                'commission_date' => optional($t->commission_date)->toDateString(),
                'is_paid' => (bool) $t->is_paid,
                'item_name' => $t->invoiceItem?->item_name,
            ]),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $month = $request->query('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $rows = DB::table('commission_transactions')
            ->where('branch_id', $branchId)
            ->whereBetween('commission_date', [$start, $end])
            ->select('user_id', 'type')
            ->selectRaw('SUM(amount) as total_amount, COUNT(*) as count')
            ->groupBy('user_id', 'type')
            ->get();

        $byUser = [];
        foreach ($rows as $r) {
            $byUser[$r->user_id] ??= ['user_id' => (int) $r->user_id, 'doctor_fee' => 0, 'staff_commission' => 0, 'referral' => 0, 'total' => 0, 'count' => 0];
            $byUser[$r->user_id][$r->type] += (float) $r->total_amount;
            $byUser[$r->user_id]['total'] += (float) $r->total_amount;
            $byUser[$r->user_id]['count'] += (int) $r->count;
        }

        $userIds = array_keys($byUser);
        $users = User::query()->whereIn('id', $userIds)->get(['id', 'uuid', 'name'])->keyBy('id');
        $data = [];
        foreach ($byUser as $uid => $row) {
            $u = $users->get($uid);
            $data[] = array_merge($row, [
                'user_uuid' => $u?->uuid,
                'user_name' => $u?->name,
            ]);
        }
        usort($data, fn ($a, $b) => $b['total'] <=> $a['total']);

        return response()->json([
            'data' => [
                'month' => $month,
                'rows' => $data,
                'totals' => [
                    'doctor_fee' => array_sum(array_column($data, 'doctor_fee')),
                    'staff_commission' => array_sum(array_column($data, 'staff_commission')),
                    'referral' => array_sum(array_column($data, 'referral')),
                    'grand_total' => array_sum(array_column($data, 'total')),
                ],
            ],
        ]);
    }

    public function preview(Visit $visit): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $invoice = $visit->invoice()->with('items')->first();
        if (! $invoice) {
            return response()->json(['data' => ['rows' => [], 'total' => 0]]);
        }

        $rows = [];
        $total = 0.0;
        foreach ($invoice->items as $item) {
            $built = $this->commissions->buildForItem($item, $branchId, now());
            foreach ($built as $b) {
                $u = User::query()->find($b['user_id']);
                $rows[] = [
                    'item_name' => $item->item_name,
                    'type' => $b['type'],
                    'user_name' => $u?->name,
                    'base_amount' => $b['base_amount'],
                    'rate' => $b['rate'],
                    'amount' => $b['amount'],
                ];
                $total += (float) $b['amount'];
            }
        }

        return response()->json(['data' => ['rows' => $rows, 'total' => round($total, 2)]]);
    }

    public function rates(): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rates = CommissionRate::query()
            ->where('branch_id', $branchId)
            ->orderBy('type')
            ->orderBy('applicable_type')
            ->get();

        return response()->json(['data' => $rates]);
    }

    public function storeRate(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'type' => ['required', Rule::in(CommissionRate::TYPES)],
            'applicable_type' => ['required', Rule::in(CommissionRate::APPLICABLE_TYPES)],
            'applicable_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['branch_id'] = $branchId;
        $data['is_active'] = $data['is_active'] ?? true;

        if ($data['rate'] === null && $data['fixed_amount'] === null) {
            return response()->json([
                'message' => 'ต้องระบุ rate (%) หรือ fixed_amount อย่างใดอย่างหนึ่ง',
                'code' => 'rate_required',
            ], 422);
        }

        $rate = CommissionRate::create($data);

        return response()->json(['data' => $rate], 201);
    }
}
