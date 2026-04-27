<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Services\Hr\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $payroll) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $q = Payroll::where('branch_id', $branchId);
        if ($request->filled('year')) {
            $q->where('period_year', (int) $request->year);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        return response()->json(['data' => $q->orderByDesc('period_year')->orderByDesc('period_month')->paginate(20)]);
    }

    public function show(Payroll $payroll): JsonResponse
    {
        $payroll->load(['items.user:id,name,employee_code,position', 'finalizedBy:id,name']);

        return response()->json(['data' => $payroll]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        $branchId = (int) app('branch.id');
        $payroll = $this->payroll->generatePreview($branchId, (int) $data['year'], (int) $data['month']);
        $payroll->load(['items.user:id,name,employee_code,position']);

        return response()->json(['data' => $payroll]);
    }

    public function adjustItem(Request $request, Payroll $payroll, PayrollItem $item): JsonResponse
    {
        if ($item->payroll_id !== $payroll->id) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'bonus' => ['nullable', 'numeric'],
            'deduction' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $row = $this->payroll->adjustItem($item, $data['bonus'] ?? null, $data['deduction'] ?? null, $data['notes'] ?? null);

        return response()->json(['data' => $row]);
    }

    public function finalize(Request $request, Payroll $payroll): JsonResponse
    {
        $row = $this->payroll->finalize($payroll, $request->user());

        return response()->json(['data' => $row]);
    }

    public function markPaid(Request $request, Payroll $payroll): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:32'],
            'payment_reference' => ['nullable', 'string', 'max:64'],
        ]);
        $row = $this->payroll->markPaid(
            $payroll,
            $request->user(),
            $data['payment_method'] ?? 'transfer',
            $data['payment_reference'] ?? null,
        );

        return response()->json(['data' => $row]);
    }
}
