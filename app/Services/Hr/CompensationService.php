<?php

namespace App\Services\Hr;

use App\Models\CompensationRule;
use App\Models\User;
use Illuminate\Support\Carbon;

class CompensationService
{
    /**
     * Resolve the active compensation rule for a user on a given date.
     * Priority: user-specific > role-based.
     */
    public function resolveRate(User $user, ?string $date = null): ?CompensationRule
    {
        $date = $date ?: Carbon::today()->toDateString();

        // 1) user-specific
        $rule = CompensationRule::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->orderByDesc('valid_from')
            ->first();
        if ($rule) {
            return $rule;
        }

        // 2) role-based — pick rule for any of user's roles
        $roleIds = $user->roles()->pluck('roles.id');
        if ($roleIds->isEmpty()) {
            return null;
        }

        return CompensationRule::whereIn('role_id', $roleIds)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->orderByDesc('valid_from')
            ->first();
    }

    /**
     * Compute base pay for a period for given rule + hours/days worked.
     */
    public function computeBasePay(CompensationRule $rule, float $hoursWorked, int $daysWorked): float
    {
        return match ($rule->type) {
            'monthly' => (float) $rule->base_amount,
            'daily' => round((float) $rule->base_amount * $daysWorked, 2),
            'hourly' => round((float) $rule->base_amount * $hoursWorked, 2),
            default => 0.0, // per_procedure / commission tracked elsewhere
        };
    }
}
