<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function dailyPL(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('date_from', now()->startOfMonth()->toDateString());
        $to = $request->query('date_to', now()->endOfMonth()->toDateString());
        $data = $this->reports->dailyPL($branchId, $from, $to);

        return response()->json(['data' => array_merge(['date_from' => $from, 'date_to' => $to], $data)]);
    }

    public function doctorPerformance(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('date_from', now()->startOfMonth()->toDateString());
        $to = $request->query('date_to', now()->endOfMonth()->toDateString());
        $data = $this->reports->doctorPerformance($branchId, $from, $to);

        return response()->json(['data' => array_merge(['date_from' => $from, 'date_to' => $to], $data)]);
    }

    public function procedurePerformance(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('date_from', now()->startOfMonth()->toDateString());
        $to = $request->query('date_to', now()->endOfMonth()->toDateString());
        $data = $this->reports->procedurePerformance($branchId, $from, $to);

        return response()->json(['data' => array_merge(['date_from' => $from, 'date_to' => $to], $data)]);
    }

    // ───── Sprint 15: 15 additional reports ─────

    public function revenueByCustomerGroup(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return response()->json(['data' => array_merge(['from' => $from, 'to' => $to], $this->reports->revenueByCustomerGroup($branchId, $from, $to))]);
    }

    public function revenueBySource(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return response()->json(['data' => array_merge(['from' => $from, 'to' => $to], $this->reports->revenueBySource($branchId, $from, $to))]);
    }

    public function cohortRetention(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $months = (int) min(24, max(1, (int) $request->query('months', 6)));

        return response()->json(['data' => $this->reports->cohortRetention($branchId, $months)]);
    }

    public function demographics(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');

        return response()->json(['data' => $this->reports->demographics($branchId)]);
    }

    public function stockValueSnapshot(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');

        return response()->json(['data' => $this->reports->stockValueSnapshot($branchId)]);
    }

    public function receivingHistory(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->subMonths(3)->toDateString());
        $to = $request->query('to', now()->toDateString());
        $supplierId = $request->query('supplier_id');

        return response()->json(['data' => $this->reports->receivingHistory($branchId, $from, $to, $supplierId ? (int) $supplierId : null)]);
    }

    public function courseOutstanding(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');

        return response()->json(['data' => $this->reports->courseOutstanding($branchId)]);
    }

    public function walletOutstanding(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');

        return response()->json(['data' => $this->reports->walletOutstanding($branchId)]);
    }

    public function memberTopupTrend(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $months = (int) min(36, max(1, (int) $request->query('months', 12)));

        return response()->json(['data' => $this->reports->memberTopupTrend($branchId, $months)]);
    }

    public function commissionPendingVsPaid(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $month = $request->query('month', now()->format('Y-m'));

        return response()->json(['data' => $this->reports->commissionPendingVsPaid($branchId, $month)]);
    }

    public function birthdayThisMonth(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $month = $request->query('month');

        return response()->json(['data' => $this->reports->birthdayThisMonth($branchId, $month ? (int) $month : null)]);
    }

    public function labTurnaround(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->subMonths(1)->toDateString());
        $to = $request->query('to', now()->toDateString());

        return response()->json(['data' => $this->reports->labTurnaround($branchId, $from, $to)]);
    }

    public function doctorUtilization(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return response()->json(['data' => $this->reports->doctorUtilization($branchId, $from, $to)]);
    }

    public function roomUtilization(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        return response()->json(['data' => $this->reports->roomUtilization($branchId, $from, $to)]);
    }

    public function photoUploadFrequency(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $days = (int) min(365, max(1, (int) $request->query('days', 90)));

        return response()->json(['data' => $this->reports->photoUploadFrequency($branchId, $days)]);
    }

    public function refundHistory(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('from', now()->subMonths(3)->toDateString());
        $to = $request->query('to', now()->toDateString());

        return response()->json(['data' => $this->reports->refundHistory($branchId, $from, $to)]);
    }

    public function paymentMix(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $from = $request->query('date_from', now()->startOfMonth()->toDateString());
        $to = $request->query('date_to', now()->endOfMonth()->toDateString());

        $byMethod = DB::table('payments')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->where('payments.branch_id', $branchId)
            ->whereDate('payments.payment_date', '>=', $from)
            ->whereDate('payments.payment_date', '<=', $to)
            ->where('invoices.status', 'paid')
            ->selectRaw('payments.method, SUM(payments.amount) as total, COUNT(*) as count')
            ->groupBy('payments.method')
            ->get();

        $byBank = DB::table('payments')
            ->leftJoin('banks', 'banks.id', '=', 'payments.bank_id')
            ->where('payments.branch_id', $branchId)
            ->where('payments.method', 'credit_card')
            ->whereDate('payments.payment_date', '>=', $from)
            ->whereDate('payments.payment_date', '<=', $to)
            ->selectRaw('banks.id as bank_id, banks.name as bank_name, SUM(payments.amount) as total, SUM(COALESCE(payments.mdr_amount, 0)) as mdr_total, COUNT(*) as count')
            ->groupBy('banks.id', 'banks.name')
            ->get();

        $grand = $byMethod->sum('total');
        $methodRows = $byMethod->map(function ($r) use ($grand) {
            return [
                'method' => $r->method,
                'total' => (float) $r->total,
                'count' => (int) $r->count,
                'pct' => $grand > 0 ? round(((float) $r->total / $grand) * 100, 2) : 0.0,
            ];
        });

        return response()->json([
            'data' => [
                'date_from' => $from,
                'date_to' => $to,
                'grand_total' => (float) $grand,
                'by_method' => $methodRows,
                'credit_card_by_bank' => $byBank->map(fn ($r) => [
                    'bank_id' => $r->bank_id,
                    'bank_name' => $r->bank_name,
                    'total' => (float) $r->total,
                    'mdr_total' => (float) $r->mdr_total,
                    'net' => round((float) $r->total - (float) $r->mdr_total, 2),
                    'count' => (int) $r->count,
                ]),
            ],
        ]);
    }
}
