<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\FollowUp;
use App\Models\MemberAccount;
use App\Models\Patient;
use App\Models\StockLevel;
use App\Services\ExpiryService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private ExpiryService $expiry,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $userId = $request->user()->id;
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        // Birthdays this month
        $birthdayThisMonth = Patient::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', $today->month)
            ->count();

        // Urgent follow-ups (priority critical/high) pending
        $urgentFollowUps = FollowUp::query()
            ->where('branch_id', $branchId)
            ->where('status', 'pending')
            ->whereIn('priority', ['critical', 'high'])
            ->count();

        // Expired stock count
        $expiredStock = StockLevel::query()
            ->whereHas('warehouse', fn ($w) => $w->where('branch_id', $branchId))
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $today->toDateString())
            ->count();

        // Red expiry stock count (within 30 days, not expired)
        $redExpiryStock = StockLevel::query()
            ->whereHas('warehouse', fn ($w) => $w->where('branch_id', $branchId))
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', $today->toDateString())
            ->whereDate('expiry_date', '<=', $today->copy()->addDays(30)->toDateString())
            ->count();

        // Low stock count
        $lowStockCount = \DB::table('products')
            ->leftJoin('stock_levels', 'stock_levels.product_id', '=', 'products.id')
            ->where('products.branch_id', $branchId)
            ->where('products.is_active', true)
            ->groupBy('products.id', 'products.reorder_point')
            ->havingRaw('COALESCE(SUM(stock_levels.quantity), 0) <= products.reorder_point')
            ->get()
            ->count();

        // Wallet liability (sum of active member balances)
        $walletLiability = (float) MemberAccount::query()
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->sum('balance');

        // Active courses with sessions remaining
        $activeCourses = Course::query()
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->where('remaining_sessions', '>', 0)
            ->count();

        // Top 5 birthdays — sort by day-of-month using portable expression
        $driver = \DB::connection()->getDriverName();
        $dayExpr = $driver === 'sqlite'
            ? "CAST(strftime('%d', date_of_birth) AS INTEGER)"
            : 'DAY(date_of_birth)';
        $topBirthdays = Patient::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', $today->month)
            ->orderByRaw("{$dayExpr} ASC")
            ->limit(5)
            ->get(['id', 'uuid', 'hn', 'first_name', 'last_name', 'date_of_birth'])
            ->map(fn ($p) => [
                'uuid' => $p->uuid,
                'hn' => $p->hn,
                'name' => trim(($p->first_name ?? '').' '.($p->last_name ?? '')),
                'birthday' => $p->date_of_birth?->format('m-d'),
                'days_until' => max(0, (int) round(abs($today->diffInDays(
                    $today->copy()->setMonth($p->date_of_birth->month)->setDay($p->date_of_birth->day),
                    false,
                )))),
            ]);

        // Top 5 urgent
        $topUrgent = FollowUp::query()
            ->where('branch_id', $branchId)
            ->where('status', 'pending')
            ->whereIn('priority', ['critical', 'high'])
            ->with('patient:id,uuid,hn,first_name,last_name')
            ->orderBy('priority')
            ->orderBy('follow_up_date')
            ->limit(5)
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'priority' => $f->priority,
                'follow_up_date' => $f->follow_up_date?->toDateString(),
                'patient_uuid' => $f->patient?->uuid,
                'patient_hn' => $f->patient?->hn,
                'patient_name' => trim(($f->patient?->first_name ?? '').' '.($f->patient?->last_name ?? '')),
            ]);

        return response()->json([
            'data' => [
                'unread_notifications' => $this->notifications->unreadCount($userId),
                'birthday_this_month' => [
                    'count' => $birthdayThisMonth,
                    'top' => $topBirthdays,
                ],
                'urgent_follow_ups' => [
                    'count' => $urgentFollowUps,
                    'top' => $topUrgent,
                ],
                'expired_stock' => $expiredStock,
                'red_expiry_stock' => $redExpiryStock,
                'low_stock_count' => $lowStockCount,
                'wallet_liability' => $walletLiability,
                'active_courses' => $activeCourses,
            ],
        ]);
    }
}
