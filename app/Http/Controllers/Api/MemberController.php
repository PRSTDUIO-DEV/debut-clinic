<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberAccount;
use App\Models\Patient;
use App\Services\MemberWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(private MemberWalletService $wallet) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $perPage = (int) min(200, max(1, $request->integer('per_page', 50)));

        $q = MemberAccount::query()
            ->where('branch_id', $branchId)
            ->with(['patient:id,uuid,hn,first_name,last_name,phone'])
            ->orderByDesc('id');

        if ($s = $request->query('q')) {
            $q->whereHas('patient', function ($p) use ($s) {
                $p->where('hn', 'like', "%{$s}%")
                    ->orWhere('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            });
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        $page = $q->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (MemberAccount $a) => $this->present($a)),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(string $patientUuid): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $patient = Patient::query()
            ->where('uuid', $patientUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $account = MemberAccount::query()
            ->where('patient_id', $patient->id)
            ->with(['patient:id,uuid,hn,first_name,last_name,phone'])
            ->first();

        return response()->json(['data' => $account ? $this->present($account) : null]);
    }

    public function transactions(Request $request, string $patientUuid): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $patient = Patient::query()
            ->where('uuid', $patientUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $account = MemberAccount::query()->where('patient_id', $patient->id)->first();
        if (! $account) {
            return response()->json(['data' => []]);
        }

        $rows = $account->transactions()
            ->with('createdBy:id,name')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'balance_before' => (float) $t->balance_before,
                'balance_after' => (float) $t->balance_after,
                'invoice_id' => $t->invoice_id,
                'notes' => $t->notes,
                'created_by' => $t->createdBy?->name,
                'created_at' => optional($t->created_at)->toIso8601String(),
            ]),
        ]);
    }

    public function deposit(Request $request, string $patientUuid): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $patient = Patient::query()
            ->where('uuid', $patientUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'package_name' => ['nullable', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $txn = $this->wallet->deposit(
            patient: $patient,
            amount: (float) $data['amount'],
            user: $request->user(),
            packageName: $data['package_name'] ?? null,
            expiresAt: $data['expires_at'] ?? null,
            notes: $data['notes'] ?? null,
        );

        $account = MemberAccount::query()->where('patient_id', $patient->id)->with('patient:id,uuid,hn,first_name,last_name,phone')->firstOrFail();

        return response()->json([
            'data' => [
                'transaction_id' => $txn->id,
                'account' => $this->present($account),
            ],
        ], 201);
    }

    public function adjust(Request $request, string $patientUuid): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $patient = Patient::query()
            ->where('uuid', $patientUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();
        $account = MemberAccount::query()
            ->where('patient_id', $patient->id)
            ->firstOrFail();

        $data = $request->validate([
            'delta' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->wallet->adjust($account, (float) $data['delta'], $data['reason'], $request->user());

        return response()->json(['data' => $this->present($account->fresh('patient'))]);
    }

    public function refund(Request $request, string $patientUuid): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $patient = Patient::query()
            ->where('uuid', $patientUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();
        $account = MemberAccount::query()
            ->where('patient_id', $patient->id)
            ->firstOrFail();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->wallet->refund($account, (float) $data['amount'], $data['invoice_id'] ?? null, $request->user(), $data['notes'] ?? null);

        return response()->json(['data' => $this->present($account->fresh('patient'))]);
    }

    private function present(MemberAccount $a): array
    {
        return [
            'id' => $a->id,
            'patient' => $a->patient ? [
                'uuid' => $a->patient->uuid,
                'hn' => $a->patient->hn,
                'name' => trim(($a->patient->first_name ?? '').' '.($a->patient->last_name ?? '')),
                'phone' => $a->patient->phone,
            ] : null,
            'balance' => (float) $a->balance,
            'total_deposit' => (float) $a->total_deposit,
            'total_used' => (float) $a->total_used,
            'package_name' => $a->package_name,
            'expires_at' => $a->expires_at?->toDateString(),
            'status' => $a->status,
            'last_topup_at' => $a->last_topup_at?->toIso8601String(),
            'last_used_at' => $a->last_used_at?->toIso8601String(),
            'lifetime_topups' => (int) $a->lifetime_topups,
        ];
    }
}
