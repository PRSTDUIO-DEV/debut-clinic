<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Daily P/L by date range.
     *
     * @return array{rows: array<int, array<string,mixed>>, totals: array<string,float>}
     */
    public function dailyPL(int $branchId, string $from, string $to): array
    {
        $invoiceAgg = DB::table('invoices')
            ->where('branch_id', $branchId)
            ->where('status', 'paid')
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $to)
            ->selectRaw('DATE(invoice_date) as d')
            ->selectRaw('SUM(total_amount) as revenue')
            ->selectRaw('SUM(total_cogs) as cogs')
            ->selectRaw('SUM(total_commission) as commission')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $mdrAgg = DB::table('payments')
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.status', 'paid')
            ->whereDate('payments.payment_date', '>=', $from)
            ->whereDate('payments.payment_date', '<=', $to)
            ->selectRaw('DATE(payments.payment_date) as d')
            ->selectRaw('SUM(payments.mdr_amount) as mdr')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $expenseAgg = DB::table('expenses')
            ->where('branch_id', $branchId)
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->whereNull('deleted_at')
            ->selectRaw('DATE(expense_date) as d')
            ->selectRaw('SUM(amount) as exp')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $rows = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);
        while ($cursor->lte($end)) {
            $d = $cursor->toDateString();
            $rev = (float) ($invoiceAgg[$d]->revenue ?? 0);
            $cogs = (float) ($invoiceAgg[$d]->cogs ?? 0);
            $com = (float) ($invoiceAgg[$d]->commission ?? 0);
            $mdr = (float) ($mdrAgg[$d]->mdr ?? 0);
            $exp = (float) ($expenseAgg[$d]->exp ?? 0);
            $gross = round($rev - $cogs - $com - $mdr, 2);
            $net = round($gross - $exp, 2);

            if ($rev > 0 || $exp > 0) {
                $rows[] = [
                    'date' => $d,
                    'revenue' => $rev,
                    'cogs' => $cogs,
                    'commission' => $com,
                    'mdr' => $mdr,
                    'gross_profit' => $gross,
                    'expenses' => $exp,
                    'net_profit' => $net,
                ];
            }
            $cursor->addDay();
        }

        $totals = [
            'revenue' => array_sum(array_column($rows, 'revenue')),
            'cogs' => array_sum(array_column($rows, 'cogs')),
            'commission' => array_sum(array_column($rows, 'commission')),
            'mdr' => array_sum(array_column($rows, 'mdr')),
            'expenses' => array_sum(array_column($rows, 'expenses')),
            'gross_profit' => array_sum(array_column($rows, 'gross_profit')),
            'net_profit' => array_sum(array_column($rows, 'net_profit')),
        ];

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Doctor performance: visits served, revenue contributed (by doctor on invoice items), commission earned.
     *
     * @return array{rows: array<int, array<string,mixed>>, totals: array<string,float|int>}
     */
    public function doctorPerformance(int $branchId, string $from, string $to): array
    {
        $rev = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.status', 'paid')
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->whereNotNull('invoice_items.doctor_id')
            ->select('invoice_items.doctor_id')
            ->selectRaw('SUM(invoice_items.total) as revenue')
            ->selectRaw('SUM(invoice_items.cost_price * invoice_items.quantity) as cogs')
            ->selectRaw('COUNT(DISTINCT invoices.visit_id) as visits')
            ->groupBy('invoice_items.doctor_id')
            ->get()
            ->keyBy('doctor_id');

        $com = DB::table('commission_transactions')
            ->where('branch_id', $branchId)
            ->whereDate('commission_date', '>=', $from)
            ->whereDate('commission_date', '<=', $to)
            ->select('user_id')
            ->selectRaw("SUM(CASE WHEN type='doctor_fee' THEN amount ELSE 0 END) as fee")
            ->selectRaw('SUM(amount) as total_commission')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $userIds = collect($rev->keys()->all())->merge($com->keys()->all())->unique()->values();

        $users = DB::table('users')->whereIn('id', $userIds)->get(['id', 'uuid', 'name'])->keyBy('id');

        $rows = [];
        foreach ($userIds as $uid) {
            $r = $rev[$uid] ?? null;
            $c = $com[$uid] ?? null;
            $u = $users[$uid] ?? null;
            $revenue = (float) ($r->revenue ?? 0);
            $cogs = (float) ($r->cogs ?? 0);
            $rows[] = [
                'user_id' => (int) $uid,
                'uuid' => $u?->uuid,
                'name' => $u?->name,
                'visits' => (int) ($r->visits ?? 0),
                'revenue' => $revenue,
                'cogs' => $cogs,
                'gross' => round($revenue - $cogs, 2),
                'doctor_fee' => (float) ($c->fee ?? 0),
                'total_commission' => (float) ($c->total_commission ?? 0),
            ];
        }
        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $totals = [
            'visits' => array_sum(array_column($rows, 'visits')),
            'revenue' => array_sum(array_column($rows, 'revenue')),
            'cogs' => array_sum(array_column($rows, 'cogs')),
            'gross' => array_sum(array_column($rows, 'gross')),
            'doctor_fee' => array_sum(array_column($rows, 'doctor_fee')),
            'total_commission' => array_sum(array_column($rows, 'total_commission')),
        ];

        return ['rows' => $rows, 'totals' => $totals];
    }

    public function procedurePerformance(int $branchId, string $from, string $to): array
    {
        $rev = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.status', 'paid')
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->where('invoice_items.item_type', 'procedure')
            ->select('invoice_items.item_id', 'invoice_items.item_name')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(invoice_items.quantity) as units')
            ->selectRaw('SUM(invoice_items.total) as revenue')
            ->selectRaw('SUM(invoice_items.cost_price * invoice_items.quantity) as cogs')
            ->groupBy('invoice_items.item_id', 'invoice_items.item_name')
            ->get();

        $rows = $rev->map(function ($r) {
            $revenue = (float) $r->revenue;
            $cogs = (float) $r->cogs;

            return [
                'procedure_id' => (int) $r->item_id,
                'name' => $r->item_name,
                'count' => (int) $r->count,
                'units' => (int) $r->units,
                'revenue' => $revenue,
                'cogs' => $cogs,
                'gross' => round($revenue - $cogs, 2),
            ];
        })->sortByDesc('revenue')->values()->all();

        $totals = [
            'count' => array_sum(array_column($rows, 'count')),
            'units' => array_sum(array_column($rows, 'units')),
            'revenue' => array_sum(array_column($rows, 'revenue')),
            'cogs' => array_sum(array_column($rows, 'cogs')),
            'gross' => array_sum(array_column($rows, 'gross')),
        ];

        return ['rows' => $rows, 'totals' => $totals];
    }

    // ───────────────────────────────────────────────────────────────────
    //  Sprint 15: 15 additional reports
    // ───────────────────────────────────────────────────────────────────

    /**
     * Revenue by customer group within date range.
     */
    public function revenueByCustomerGroup(int $branchId, string $from, string $to): array
    {
        $rows = DB::table('invoices')
            ->join('patients', 'patients.id', '=', 'invoices.patient_id')
            ->leftJoin('customer_groups as cg', 'cg.id', '=', 'patients.customer_group_id')
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.status', 'paid')
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->select('cg.id as group_id', 'cg.name as group_name')
            ->selectRaw('SUM(invoices.total_amount) as revenue, COUNT(DISTINCT invoices.patient_id) as patients, COUNT(*) as invoices')
            ->groupBy('cg.id', 'cg.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'group_id' => $r->group_id ? (int) $r->group_id : null,
                'name' => $r->group_name ?: '— ไม่ระบุกลุ่ม —',
                'revenue' => (float) $r->revenue,
                'patients' => (int) $r->patients,
                'invoices' => (int) $r->invoices,
                'avg_per_patient' => (int) $r->patients > 0 ? round((float) $r->revenue / (int) $r->patients, 2) : 0,
            ])
            ->values()
            ->all();

        return ['rows' => $rows, 'totals' => [
            'revenue' => array_sum(array_column($rows, 'revenue')),
            'patients' => array_sum(array_column($rows, 'patients')),
            'invoices' => array_sum(array_column($rows, 'invoices')),
        ]];
    }

    /**
     * Revenue by patient source (walk_in / referral / online / etc).
     */
    public function revenueBySource(int $branchId, string $from, string $to): array
    {
        $rows = DB::table('invoices')
            ->join('patients', 'patients.id', '=', 'invoices.patient_id')
            ->where('invoices.branch_id', $branchId)
            ->where('invoices.status', 'paid')
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $to)
            ->select('patients.source')
            ->selectRaw('SUM(invoices.total_amount) as revenue, COUNT(*) as count')
            ->groupBy('patients.source')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'source' => $r->source ?: '—',
                'revenue' => (float) $r->revenue,
                'count' => (int) $r->count,
            ])
            ->all();

        return ['rows' => $rows, 'totals' => ['revenue' => array_sum(array_column($rows, 'revenue'))]];
    }

    /**
     * Cohort retention: patients grouped by first-visit month, counted by repeat visits.
     */
    public function cohortRetention(int $branchId, int $months = 6): array
    {
        $today = Carbon::today();
        $start = $today->copy()->subMonths($months)->startOfMonth();

        $patients = DB::table('patients')
            ->where('branch_id', $branchId)
            ->whereNotNull('created_at')
            ->whereDate('created_at', '>=', $start->toDateString())
            ->get(['id', 'created_at', 'visit_count']);

        $rows = [];
        foreach ($patients->groupBy(fn ($p) => Carbon::parse($p->created_at)->format('Y-m')) as $cohort => $group) {
            $count1 = $group->where('visit_count', '>=', 1)->count();
            $count2 = $group->where('visit_count', '>=', 2)->count();
            $count3 = $group->where('visit_count', '>=', 3)->count();
            $count4plus = $group->where('visit_count', '>=', 4)->count();

            $rows[] = [
                'cohort' => $cohort,
                'cohort_size' => $group->count(),
                'visited_1plus' => $count1,
                'visited_2plus' => $count2,
                'visited_3plus' => $count3,
                'visited_4plus' => $count4plus,
                'retention_2_pct' => $group->count() > 0 ? round($count2 / $group->count() * 100, 1) : 0,
                'retention_3_pct' => $group->count() > 0 ? round($count3 / $group->count() * 100, 1) : 0,
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($b['cohort'], $a['cohort']));

        return ['rows' => $rows];
    }

    /**
     * Patient demographics: gender + age bucket + customer group.
     */
    public function demographics(int $branchId): array
    {
        $today = Carbon::today();
        $patients = DB::table('patients')
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->get(['id', 'gender', 'date_of_birth', 'customer_group_id']);

        $byGender = ['male' => 0, 'female' => 0, 'other' => 0, 'unknown' => 0];
        $byAge = ['<20' => 0, '20-29' => 0, '30-39' => 0, '40-49' => 0, '50-59' => 0, '60+' => 0, 'unknown' => 0];
        foreach ($patients as $p) {
            $gender = $p->gender ?: 'unknown';
            $byGender[$gender] = ($byGender[$gender] ?? 0) + 1;

            if (! $p->date_of_birth) {
                $byAge['unknown']++;

                continue;
            }
            $age = Carbon::parse($p->date_of_birth)->diffInYears($today);
            $bucket = match (true) {
                $age < 20 => '<20',
                $age < 30 => '20-29',
                $age < 40 => '30-39',
                $age < 50 => '40-49',
                $age < 60 => '50-59',
                default => '60+',
            };
            $byAge[$bucket]++;
        }

        $byCustomerGroup = DB::table('patients')
            ->leftJoin('customer_groups', 'customer_groups.id', '=', 'patients.customer_group_id')
            ->where('patients.branch_id', $branchId)
            ->whereNull('patients.deleted_at')
            ->select('customer_groups.id as group_id', 'customer_groups.name')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('customer_groups.id', 'customer_groups.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'group_id' => $r->group_id ? (int) $r->group_id : null,
                'name' => $r->name ?: '— ไม่ระบุ —',
                'count' => (int) $r->count,
            ])
            ->all();

        return [
            'total' => $patients->count(),
            'by_gender' => $byGender,
            'by_age' => $byAge,
            'by_customer_group' => $byCustomerGroup,
        ];
    }

    /**
     * Stock value snapshot per warehouse + per product category.
     */
    public function stockValueSnapshot(int $branchId): array
    {
        $byWarehouse = DB::table('stock_levels')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->where('warehouses.branch_id', $branchId)
            ->select('warehouses.id', 'warehouses.name', 'warehouses.type')
            ->selectRaw('SUM(stock_levels.quantity * stock_levels.cost_price) as value, COUNT(stock_levels.id) as lot_count')
            ->groupBy('warehouses.id', 'warehouses.name', 'warehouses.type')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($r) => [
                'warehouse_id' => (int) $r->id,
                'name' => $r->name,
                'type' => $r->type,
                'value' => (float) $r->value,
                'lot_count' => (int) $r->lot_count,
            ])
            ->all();

        $byCategory = DB::table('stock_levels')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'products.category_id')
            ->where('warehouses.branch_id', $branchId)
            ->select('pc.id as cat_id', 'pc.name as cat_name')
            ->selectRaw('SUM(stock_levels.quantity * stock_levels.cost_price) as value, SUM(stock_levels.quantity) as units')
            ->groupBy('pc.id', 'pc.name')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($r) => [
                'category_id' => $r->cat_id ? (int) $r->cat_id : null,
                'name' => $r->cat_name ?: '— ไม่ระบุหมวด —',
                'value' => (float) $r->value,
                'units' => (int) $r->units,
            ])
            ->all();

        return [
            'as_of' => Carbon::today()->toDateString(),
            'by_warehouse' => $byWarehouse,
            'by_category' => $byCategory,
            'total_value' => array_sum(array_column($byWarehouse, 'value')),
        ];
    }

    /**
     * Receiving history per supplier.
     */
    public function receivingHistory(int $branchId, string $from, string $to, ?int $supplierId = null): array
    {
        $q = DB::table('goods_receivings')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'goods_receivings.supplier_id')
            ->where('goods_receivings.branch_id', $branchId)
            ->whereDate('goods_receivings.receive_date', '>=', $from)
            ->whereDate('goods_receivings.receive_date', '<=', $to)
            ->whereNull('goods_receivings.deleted_at');
        if ($supplierId) {
            $q->where('goods_receivings.supplier_id', $supplierId);
        }

        $rows = $q->select('suppliers.id as supplier_id', 'suppliers.name as supplier_name')
            ->selectRaw('SUM(goods_receivings.total_amount) as total, COUNT(*) as count')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'supplier_id' => $r->supplier_id ? (int) $r->supplier_id : null,
                'name' => $r->supplier_name ?: '— ไม่ระบุ —',
                'total' => (float) $r->total,
                'count' => (int) $r->count,
            ])
            ->all();

        return ['rows' => $rows, 'totals' => [
            'total' => array_sum(array_column($rows, 'total')),
            'count' => array_sum(array_column($rows, 'count')),
        ]];
    }

    /**
     * Course outstanding sessions aging by patient.
     */
    public function courseOutstanding(int $branchId): array
    {
        $today = Carbon::today();
        $rows = DB::table('courses')
            ->join('patients', 'patients.id', '=', 'courses.patient_id')
            ->where('courses.branch_id', $branchId)
            ->where('courses.status', 'active')
            ->where('courses.remaining_sessions', '>', 0)
            ->select('courses.id', 'courses.name', 'courses.total_sessions', 'courses.used_sessions', 'courses.remaining_sessions', 'courses.expires_at',
                'patients.uuid as patient_uuid', 'patients.hn', 'patients.first_name', 'patients.last_name')
            ->orderBy('courses.expires_at')
            ->get()
            ->map(function ($r) use ($today) {
                $daysToExpiry = $r->expires_at ? Carbon::parse($r->expires_at)->diffInDays($today, false) : null;

                return [
                    'course_id' => (int) $r->id,
                    'name' => $r->name,
                    'total_sessions' => (int) $r->total_sessions,
                    'used' => (int) $r->used_sessions,
                    'remaining' => (int) $r->remaining_sessions,
                    'expires_at' => $r->expires_at,
                    'days_to_expiry' => $daysToExpiry !== null ? abs((int) round($daysToExpiry)) : null,
                    'patient' => [
                        'uuid' => $r->patient_uuid,
                        'hn' => $r->hn,
                        'name' => trim(($r->first_name ?? '').' '.($r->last_name ?? '')),
                    ],
                ];
            })
            ->all();

        return ['rows' => $rows, 'totals' => [
            'courses' => count($rows),
            'sessions' => array_sum(array_column($rows, 'remaining')),
        ]];
    }

    /**
     * Wallet outstanding aging — active member balances + days since last topup.
     */
    public function walletOutstanding(int $branchId): array
    {
        $today = Carbon::today();
        $rows = DB::table('member_accounts')
            ->join('patients', 'patients.id', '=', 'member_accounts.patient_id')
            ->where('member_accounts.branch_id', $branchId)
            ->where('member_accounts.status', 'active')
            ->where('member_accounts.balance', '>', 0)
            ->select('member_accounts.id', 'member_accounts.balance', 'member_accounts.last_topup_at', 'member_accounts.last_used_at',
                'patients.uuid', 'patients.hn', 'patients.first_name', 'patients.last_name')
            ->orderByDesc('member_accounts.balance')
            ->get()
            ->map(function ($r) use ($today) {
                $daysSinceTopup = $r->last_topup_at ? abs((int) round(Carbon::parse($r->last_topup_at)->diffInDays($today, false))) : null;

                return [
                    'member_id' => (int) $r->id,
                    'patient' => [
                        'uuid' => $r->uuid,
                        'hn' => $r->hn,
                        'name' => trim(($r->first_name ?? '').' '.($r->last_name ?? '')),
                    ],
                    'balance' => (float) $r->balance,
                    'last_topup_at' => $r->last_topup_at,
                    'days_since_topup' => $daysSinceTopup,
                ];
            })
            ->all();

        return ['rows' => $rows, 'totals' => [
            'count' => count($rows),
            'balance' => array_sum(array_column($rows, 'balance')),
        ]];
    }

    /**
     * Member topup trend (per month).
     */
    public function memberTopupTrend(int $branchId, int $months = 12): array
    {
        $start = Carbon::today()->subMonths($months - 1)->startOfMonth();

        $rows = DB::table('member_transactions')
            ->join('member_accounts', 'member_accounts.id', '=', 'member_transactions.member_account_id')
            ->where('member_accounts.branch_id', $branchId)
            ->where('member_transactions.type', 'deposit')
            ->whereDate('member_transactions.created_at', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(member_transactions.created_at, '%Y-%m') as month, SUM(member_transactions.amount) as total, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // SQLite fallback: DATE_FORMAT not supported
        if ($rows->isEmpty() && DB::connection()->getDriverName() === 'sqlite') {
            $rows = DB::table('member_transactions')
                ->join('member_accounts', 'member_accounts.id', '=', 'member_transactions.member_account_id')
                ->where('member_accounts.branch_id', $branchId)
                ->where('member_transactions.type', 'deposit')
                ->whereDate('member_transactions.created_at', '>=', $start->toDateString())
                ->selectRaw("strftime('%Y-%m', member_transactions.created_at) as month, SUM(member_transactions.amount) as total, COUNT(*) as count")
                ->groupBy('month')
                ->orderBy('month')
                ->get();
        }

        return [
            'rows' => $rows->map(fn ($r) => [
                'month' => $r->month,
                'total' => (float) $r->total,
                'count' => (int) $r->count,
            ])->all(),
        ];
    }

    /**
     * Commission pending vs paid per user.
     */
    public function commissionPendingVsPaid(int $branchId, ?string $month = null): array
    {
        $month = $month ?: Carbon::today()->format('Y-m');
        $start = $month.'-01';
        $end = date('Y-m-t', strtotime($start));

        $rows = DB::table('commission_transactions')
            ->leftJoin('users', 'users.id', '=', 'commission_transactions.user_id')
            ->where('commission_transactions.branch_id', $branchId)
            ->whereDate('commission_transactions.commission_date', '>=', $start)
            ->whereDate('commission_transactions.commission_date', '<=', $end)
            ->select('users.id as user_id', 'users.name')
            ->selectRaw('SUM(CASE WHEN is_paid = 1 THEN amount ELSE 0 END) as paid')
            ->selectRaw('SUM(CASE WHEN is_paid = 0 THEN amount ELSE 0 END) as pending')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('pending')
            ->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id ? (int) $r->user_id : null,
                'name' => $r->name ?: '?',
                'paid' => (float) $r->paid,
                'pending' => (float) $r->pending,
                'count' => (int) $r->count,
                'total' => (float) $r->paid + (float) $r->pending,
            ])
            ->all();

        return ['period' => $month, 'rows' => $rows, 'totals' => [
            'paid' => array_sum(array_column($rows, 'paid')),
            'pending' => array_sum(array_column($rows, 'pending')),
        ]];
    }

    /**
     * Birthday this month — patients sorted by day-of-month.
     */
    public function birthdayThisMonth(int $branchId, ?int $month = null): array
    {
        $month = $month ?: (int) Carbon::today()->format('m');
        $today = Carbon::today();

        $driver = DB::connection()->getDriverName();
        $dayExpr = $driver === 'sqlite'
            ? "CAST(strftime('%d', date_of_birth) AS INTEGER)"
            : 'DAY(date_of_birth)';

        $patients = DB::table('patients')
            ->where('branch_id', $branchId)
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', $month)
            ->whereNull('deleted_at')
            ->orderByRaw("{$dayExpr} ASC")
            ->select('uuid', 'hn', 'first_name', 'last_name', 'date_of_birth', 'phone', 'line_id')
            ->limit(500)
            ->get()
            ->map(function ($p) use ($today) {
                $dob = Carbon::parse($p->date_of_birth);
                $age = $dob->copy()->setYear((int) $today->format('Y'))->diffInYears($dob);

                return [
                    'uuid' => $p->uuid,
                    'hn' => $p->hn,
                    'name' => trim(($p->first_name ?? '').' '.($p->last_name ?? '')),
                    'birthday' => $dob->format('m-d'),
                    'turning_age' => $today->format('Y') - $dob->format('Y'),
                    'phone' => $p->phone,
                    'line_id' => $p->line_id,
                ];
            })
            ->all();

        return ['month' => sprintf('%02d', $month), 'rows' => $patients];
    }

    /**
     * Lab turnaround time: days from order_date to result_date.
     */
    public function labTurnaround(int $branchId, string $from, string $to): array
    {
        $orders = DB::table('lab_orders')
            ->where('branch_id', $branchId)
            ->where('status', 'completed')
            ->whereNotNull('result_date')
            ->whereDate('ordered_at', '>=', $from)
            ->whereDate('ordered_at', '<=', $to)
            ->select('id', 'order_no', 'ordered_at', 'result_date')
            ->get();

        $rows = $orders->map(function ($o) {
            $turnaround = abs((int) round(Carbon::parse($o->ordered_at)->diffInDays(Carbon::parse($o->result_date), false)));

            return [
                'order_id' => (int) $o->id,
                'order_no' => $o->order_no,
                'ordered_at' => $o->ordered_at,
                'result_date' => $o->result_date,
                'turnaround_days' => $turnaround,
            ];
        })->all();

        $turnarounds = array_column($rows, 'turnaround_days');
        $avg = count($turnarounds) > 0 ? round(array_sum($turnarounds) / count($turnarounds), 2) : 0;

        return ['rows' => $rows, 'totals' => [
            'count' => count($rows),
            'avg_days' => $avg,
            'max_days' => count($turnarounds) > 0 ? max($turnarounds) : 0,
        ]];
    }

    /**
     * Doctor utilization: visits per day per doctor.
     */
    public function doctorUtilization(int $branchId, string $from, string $to): array
    {
        $rows = DB::table('visits')
            ->join('users', 'users.id', '=', 'visits.doctor_id')
            ->where('visits.branch_id', $branchId)
            ->whereNotNull('visits.doctor_id')
            ->whereDate('visits.visit_date', '>=', $from)
            ->whereDate('visits.visit_date', '<=', $to)
            ->select('users.id', 'users.name')
            ->selectRaw('COUNT(*) as visits, COUNT(DISTINCT visits.visit_date) as active_days')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('visits')
            ->get()
            ->map(fn ($r) => [
                'user_id' => (int) $r->id,
                'name' => $r->name,
                'visits' => (int) $r->visits,
                'active_days' => (int) $r->active_days,
                'avg_per_day' => $r->active_days > 0 ? round($r->visits / $r->active_days, 2) : 0,
            ])
            ->all();

        return ['rows' => $rows];
    }

    /**
     * Room utilization: visits per room.
     */
    public function roomUtilization(int $branchId, string $from, string $to): array
    {
        $rows = DB::table('visits')
            ->leftJoin('rooms', 'rooms.id', '=', 'visits.room_id')
            ->where('visits.branch_id', $branchId)
            ->whereDate('visits.visit_date', '>=', $from)
            ->whereDate('visits.visit_date', '<=', $to)
            ->select('rooms.id', 'rooms.name')
            ->selectRaw('COUNT(*) as visits, COUNT(DISTINCT visits.visit_date) as active_days')
            ->groupBy('rooms.id', 'rooms.name')
            ->orderByDesc('visits')
            ->get()
            ->map(fn ($r) => [
                'room_id' => $r->id ? (int) $r->id : null,
                'name' => $r->name ?: '— ไม่ระบุห้อง —',
                'visits' => (int) $r->visits,
                'active_days' => (int) $r->active_days,
            ])
            ->all();

        return ['rows' => $rows];
    }

    /**
     * Photo upload frequency last N days.
     */
    public function photoUploadFrequency(int $branchId, int $days = 90): array
    {
        $start = Carbon::today()->subDays($days)->toDateString();

        $rows = DB::table('patient_photos')
            ->join('patients', 'patients.id', '=', 'patient_photos.patient_id')
            ->where('patient_photos.branch_id', $branchId)
            ->whereDate('patient_photos.created_at', '>=', $start)
            ->whereNull('patient_photos.deleted_at')
            ->select('patients.uuid', 'patients.hn', 'patients.first_name', 'patients.last_name')
            ->selectRaw('COUNT(*) as photo_count')
            ->groupBy('patients.id', 'patients.uuid', 'patients.hn', 'patients.first_name', 'patients.last_name')
            ->orderByDesc('photo_count')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'uuid' => $r->uuid,
                'hn' => $r->hn,
                'name' => trim(($r->first_name ?? '').' '.($r->last_name ?? '')),
                'photo_count' => (int) $r->photo_count,
            ])
            ->all();

        $total = (int) DB::table('patient_photos')
            ->where('branch_id', $branchId)
            ->whereDate('created_at', '>=', $start)
            ->whereNull('deleted_at')
            ->count();

        return ['rows' => $rows, 'totals' => ['total_uploads' => $total, 'days' => $days]];
    }

    /**
     * Refund history.
     */
    public function refundHistory(int $branchId, string $from, string $to): array
    {
        $rows = DB::table('member_transactions')
            ->join('member_accounts', 'member_accounts.id', '=', 'member_transactions.member_account_id')
            ->join('patients', 'patients.id', '=', 'member_accounts.patient_id')
            ->where('member_accounts.branch_id', $branchId)
            ->where('member_transactions.type', 'refund')
            ->whereDate('member_transactions.created_at', '>=', $from)
            ->whereDate('member_transactions.created_at', '<=', $to)
            ->select('member_transactions.id', 'member_transactions.amount', 'member_transactions.notes', 'member_transactions.created_at',
                'patients.uuid', 'patients.hn', 'patients.first_name', 'patients.last_name')
            ->orderByDesc('member_transactions.created_at')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'amount' => (float) $r->amount,
                'notes' => $r->notes,
                'created_at' => $r->created_at,
                'patient' => [
                    'uuid' => $r->uuid,
                    'hn' => $r->hn,
                    'name' => trim(($r->first_name ?? '').' '.($r->last_name ?? '')),
                ],
            ])
            ->all();

        return ['rows' => $rows, 'totals' => [
            'count' => count($rows),
            'amount' => array_sum(array_column($rows, 'amount')),
        ]];
    }
}
