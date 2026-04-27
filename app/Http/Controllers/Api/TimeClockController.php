<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\TimeClock;
use App\Models\User;
use App\Services\Hr\TimeClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TimeClockController extends Controller
{
    public function __construct(private TimeClockService $tc) {}

    /**
     * PIN-based clock-in (kiosk mode, no Sanctum auth).
     */
    public function clockInPin(Request $request): JsonResponse
    {
        return $this->pinAction($request, 'in');
    }

    public function clockOutPin(Request $request): JsonResponse
    {
        return $this->pinAction($request, 'out');
    }

    private function pinAction(Request $request, string $action): JsonResponse
    {
        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:64'],
            'pin' => ['required', 'string', 'min:4', 'max:6'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $user = User::where('employee_code', $data['employee_code'])->first();
        if (! $user || ! $user->pin_hash || ! Hash::check($data['pin'], $user->pin_hash) || ! $user->is_active) {
            throw ValidationException::withMessages(['pin' => 'รหัสพนักงานหรือ PIN ไม่ถูกต้อง']);
        }

        $branch = Branch::findOrFail($data['branch_id']);
        $row = $action === 'in' ? $this->tc->clockIn($user, $branch, 'kiosk') : $this->tc->clockOut($user);

        return response()->json([
            'data' => [
                'user' => ['name' => $user->name, 'employee_code' => $user->employee_code],
                'action' => $action,
                'time' => $action === 'in' ? $row->clock_in : $row->clock_out,
                'late_minutes' => $row->late_minutes,
                'overtime_minutes' => $row->overtime_minutes,
                'total_minutes' => $row->total_minutes,
            ],
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $open = $this->tc->currentOpen($user);
        $today = $this->tc->dailySummary($user, now()->toDateString());

        return response()->json(['data' => [
            'open' => $open,
            'today' => $today,
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $q = TimeClock::with('user:id,name,employee_code')
            ->where('branch_id', $branchId);
        if ($request->filled('user_id')) {
            $q->where('user_id', (int) $request->user_id);
        }
        if ($request->filled('from')) {
            $q->whereDate('clock_in', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('clock_in', '<=', $request->to);
        }

        return response()->json(['data' => $q->orderByDesc('clock_in')->paginate(50)]);
    }

    public function manualEntry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'clock_in' => ['required', 'date'],
            'clock_out' => ['required', 'date', 'after:clock_in'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $branchId = (int) app('branch.id');
        $branch = Branch::findOrFail($branchId);
        $user = User::findOrFail($data['user_id']);

        $row = $this->tc->manualEntry($user, $branch, $data['clock_in'], $data['clock_out'], $data['reason'] ?? null, $request->user());

        return response()->json(['data' => $row], 201);
    }

    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json(['data' => $this->tc->monthlySummary((int) $data['user_id'], (int) $data['year'], (int) $data['month'])]);
    }
}
