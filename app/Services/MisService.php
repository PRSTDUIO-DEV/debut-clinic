<?php

namespace App\Services;

use App\Services\Cache\CacheService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MisService
{
    public function __construct(private ?CacheService $cache = null) {}

    /**
     * Executive KPI dashboard for the requested period (cached 60s per branch+period).
     *
     * @return array<string, mixed>
     */
    public function dashboard(int $branchId, string $period = 'month'): array
    {
        $cache = $this->cache ?? app(CacheService::class);

        return $cache->remember($branchId, 'mis.dashboard', CacheService::TTL_MIS, function () use ($branchId, $period) {
            return $this->buildDashboard($branchId, $period);
        }, ['period' => $period]);
    }

    private function buildDashboard(int $branchId, string $period): array
    {
        [$from, $to, $prevFrom, $prevTo] = $this->resolvePeriod($period);

        $current = $this->kpiSnapshot($branchId, $from, $to);
        $previous = $this->kpiSnapshot($branchId, $prevFrom, $prevTo);

        $delta = function (float $cur, float $prev): array {
            $diff = $cur - $prev;
            $pct = $prev > 0 ? round(($diff / $prev) * 100, 1) : null;

            return ['diff' => round($diff, 2), 'pct' => $pct];
        };

        return [
            'period' => ['code' => $period, 'from' => $from, 'to' => $to, 'prev_from' => $prevFrom, 'prev_to' => $prevTo],
            'kpis' => [
                'revenue' => array_merge(['current' => $current['revenue'], 'previous' => $previous['revenue']], $delta($current['revenue'], $previous['revenue'])),
                'visits' => array_merge(['current' => $current['visits'], 'previous' => $previous['visits']], $delta($current['visits'], $previous['visits'])),
                'new_patients' => array_merge(['current' => $current['new_patients'], 'previous' => $previous['new_patients']], $delta($current['new_patients'], $previous['new_patients'])),
                'avg_ticket' => array_merge(['current' => $current['avg_ticket'], 'previous' => $previous['avg_ticket']], $delta($current['avg_ticket'], $previous['avg_ticket'])),
                'gross_profit' => array_merge(['current' => $current['gross_profit'], 'previous' => $previous['gross_profit']], $delta($current['gross_profit'], $previous['gross_profit'])),
            ],
            'snapshot' => [
                'cash_on_hand' => $this->cashOnHand($branchId),
                'wallet_liability' => $this->walletLiability($branchId),
                'stock_value' => $this->stockValue($branchId),
                'active_courses' => $this->activeCourses($branchId),
            ],
        ];
    }

    /**
     * Daily revenue + visits trend over last N days.
     */
    public function charts(int $branchId, int $days = 30): array
    {
        $from = Carbon::today()->subDays($days - 1)->toDateString();
        $to = Carbon::today()->toDateString();

        $invoices = DB::table('invoices')
            ->where('branch_id', $branchId)
            ->where('status', 'paid')
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $to)
            ->selectRaw('DATE(invoice_date) as d, SUM(total_amount) as revenue, SUM(total_cogs) as cogs, SUM(total_commission) as commission, COUNT(*) as inv_count')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $visits = DB::table('visits')
            ->where('branch_id', $branchId)
            ->whereDate('visit_date', '>=', $from)
            ->whereDate('visit_date', '<=', $to)
            ->selectRaw('DATE(visit_date) as d, COUNT(*) as count')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $rows = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);
        while ($cursor->lte($end)) {
            $d = $cursor->toDateString();
            $inv = $invoices[$d] ?? null;
            $rev = (float) ($inv->revenue ?? 0);
            $cogs = (float) ($inv->cogs ?? 0);
            $com = (float) ($inv->commission ?? 0);
            $rows[] = [
                'date' => $d,
                'revenue' => $rev,
                'gross_profit' => round($rev - $cogs - $com, 2),
                'visits' => (int) ($visits[$d]->count ?? 0),
                'invoices' => (int) ($inv->inv_count ?? 0),
            ];
            $cursor->addDay();
        }

        return ['from' => $from, 'to' => $to, 'rows' => $rows];
    }

    public function topProcedures(int $branchId, string $from, string $to, int $limit = 10): array
    {
        return DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.status', 'paid')
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->where('invoice_items.item_type', 'procedure')
            ->select('invoice_items.item_id', 'invoice_items.item_name')
            ->selectRaw('COUNT(*) as count, SUM(invoice_items.quantity) as units, SUM(invoice_items.total) as revenue, SUM(invoice_items.cost_price * invoice_items.quantity) as cogs')
            ->groupBy('invoice_items.item_id', 'invoice_items.item_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'item_id' => (int) $r->item_id,
                'name' => $r->item_name,
                'count' => (int) $r->count,
                'units' => (int) $r->units,
                'revenue' => (float) $r->revenue,
                'cogs' => (float) $r->cogs,
                'gross' => round((float) $r->revenue - (float) $r->cogs, 2),
            ])
            ->all();
    }

    public function topDoctors(int $branchId, string $from, string $to, int $limit = 10): array
    {
        $rev = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.status', 'paid')
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->whereNotNull('invoice_items.doctor_id')
            ->select('invoice_items.doctor_id')
            ->selectRaw('SUM(invoice_items.total) as revenue, COUNT(DISTINCT invoices.visit_id) as visits')
            ->groupBy('invoice_items.doctor_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $userIds = $rev->pluck('doctor_id');
        $users = DB::table('users')->whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

        return $rev->map(fn ($r) => [
            'user_id' => (int) $r->doctor_id,
            'name' => $users[$r->doctor_id]->name ?? '?',
            'visits' => (int) $r->visits,
            'revenue' => (float) $r->revenue,
        ])->all();
    }

    public function topCustomerGroups(int $branchId, string $from, string $to): array
    {
        return DB::table('invoices')
            ->join('patients', 'patients.id', '=', 'invoices.patient_id')
            ->leftJoin('customer_groups', 'customer_groups.id', '=', 'patients.customer_group_id')
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.status', 'paid')
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->select('patients.customer_group_id', 'customer_groups.name as group_name')
            ->selectRaw('SUM(invoices.total_amount) as revenue, COUNT(DISTINCT invoices.patient_id) as patient_count, COUNT(*) as inv_count')
            ->groupBy('patients.customer_group_id', 'customer_groups.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'customer_group_id' => $r->customer_group_id ? (int) $r->customer_group_id : null,
                'name' => $r->group_name ?: '— ไม่ระบุกลุ่ม —',
                'revenue' => (float) $r->revenue,
                'patient_count' => (int) $r->patient_count,
                'invoice_count' => (int) $r->inv_count,
            ])
            ->all();
    }

    public function topPatients(int $branchId, string $from, string $to, int $limit = 10): array
    {
        return DB::table('invoices')
            ->join('patients', 'patients.id', '=', 'invoices.patient_id')
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.status', 'paid')
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->select('patients.uuid', 'patients.hn', 'patients.first_name', 'patients.last_name')
            ->selectRaw('SUM(invoices.total_amount) as revenue, COUNT(*) as visit_count')
            ->groupBy('patients.id', 'patients.uuid', 'patients.hn', 'patients.first_name', 'patients.last_name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'uuid' => $r->uuid,
                'hn' => $r->hn,
                'name' => trim(($r->first_name ?? '').' '.($r->last_name ?? '')),
                'revenue' => (float) $r->revenue,
                'visit_count' => (int) $r->visit_count,
            ])
            ->all();
    }

    private function kpiSnapshot(int $branchId, string $from, string $to): array
    {
        $invAgg = DB::table('invoices')
            ->where('branch_id', $branchId)
            ->where('status', 'paid')
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $to)
            ->selectRaw('SUM(total_amount) as revenue, SUM(total_cogs) as cogs, SUM(total_commission) as commission, COUNT(*) as inv_count')
            ->first();

        $visitCount = (int) DB::table('visits')
            ->where('branch_id', $branchId)
            ->whereDate('visit_date', '>=', $from)
            ->whereDate('visit_date', '<=', $to)
            ->count();

        $newPatients = (int) DB::table('patients')
            ->where('branch_id', $branchId)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $revenue = (float) ($invAgg->revenue ?? 0);
        $cogs = (float) ($invAgg->cogs ?? 0);
        $commission = (float) ($invAgg->commission ?? 0);
        $invCount = (int) ($invAgg->inv_count ?? 0);

        return [
            'revenue' => $revenue,
            'visits' => $visitCount,
            'new_patients' => $newPatients,
            'avg_ticket' => $invCount > 0 ? round($revenue / $invCount, 2) : 0,
            'gross_profit' => round($revenue - $cogs - $commission, 2),
        ];
    }

    private function cashOnHand(int $branchId): float
    {
        // Use accounting balance for cash account (1100) if available; fall back to closing.
        $cash = (float) DB::table('accounting_entries')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'accounting_entries.debit_account_id')
            ->where('accounting_entries.branch_id', $branchId)
            ->where('coa.code', '1100')
            ->sum('accounting_entries.amount');
        $cashOut = (float) DB::table('accounting_entries')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'accounting_entries.credit_account_id')
            ->where('accounting_entries.branch_id', $branchId)
            ->where('coa.code', '1100')
            ->sum('accounting_entries.amount');

        return round($cash - $cashOut, 2);
    }

    private function walletLiability(int $branchId): float
    {
        return (float) DB::table('member_accounts')
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->sum('balance');
    }

    private function stockValue(int $branchId): float
    {
        return (float) DB::table('stock_levels')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->where('warehouses.branch_id', $branchId)
            ->selectRaw('SUM(stock_levels.quantity * stock_levels.cost_price) as v')
            ->value('v');
    }

    private function activeCourses(int $branchId): int
    {
        return (int) DB::table('courses')
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->where('remaining_sessions', '>', 0)
            ->count();
    }

    /**
     * @return array{0:string, 1:string, 2:string, 3:string} from / to / prevFrom / prevTo
     */
    private function resolvePeriod(string $period): array
    {
        $today = Carbon::today();
        switch ($period) {
            case 'today':
                return [$today->toDateString(), $today->toDateString(),
                    $today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()];
            case 'week':
                $start = $today->copy()->startOfWeek();
                $prevStart = $start->copy()->subWeek();

                return [$start->toDateString(), $today->toDateString(),
                    $prevStart->toDateString(), $prevStart->copy()->endOfWeek()->toDateString()];
            case 'year':
                $start = $today->copy()->startOfYear();
                $prevStart = $start->copy()->subYear();

                return [$start->toDateString(), $today->toDateString(),
                    $prevStart->toDateString(), $prevStart->copy()->endOfYear()->toDateString()];
            case 'month':
            default:
                $start = $today->copy()->startOfMonth();
                $prevStart = $start->copy()->subMonth();

                return [$start->toDateString(), $today->toDateString(),
                    $prevStart->toDateString(), $prevStart->copy()->endOfMonth()->toDateString()];
        }
    }
}
