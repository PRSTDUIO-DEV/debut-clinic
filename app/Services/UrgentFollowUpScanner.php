<?php

namespace App\Services;

use App\Models\Course;
use App\Models\FollowUp;
use App\Models\FollowUpRule;
use App\Models\MemberAccount;
use App\Models\Patient;
use Illuminate\Support\Carbon;

class UrgentFollowUpScanner
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Scan rules for a branch, classify follow-ups + notify.
     * Returns count of notifications written.
     */
    public function run(?int $branchId = null): int
    {
        $rules = FollowUpRule::query()->where('is_active', true);
        if ($branchId) {
            $rules->where('branch_id', $branchId);
        }

        $written = 0;
        foreach ($rules->get() as $rule) {
            $written += $this->applyRule($rule);
            $rule->last_run_at = now();
            $rule->save();
        }

        return $written;
    }

    public function applyRule(FollowUpRule $rule): int
    {
        $written = 0;
        $candidates = $this->resolveCandidates($rule);

        foreach ($candidates as $row) {
            // row = ['patient_id', 'patient_name', 'reason', 'follow_up_id'?]
            $title = "[{$rule->priority}] {$rule->name}";
            $body = "ผู้ป่วย {$row['patient_name']} — {$row['reason']}";
            $severity = match ($rule->priority) {
                'critical' => 'critical',
                'high' => 'warning',
                default => 'info',
            };

            // Update follow_up.priority if applicable
            if (! empty($row['follow_up_id'])) {
                FollowUp::query()
                    ->where('id', $row['follow_up_id'])
                    ->where('priority', '!=', $rule->priority)
                    ->update(['priority' => $rule->priority]);
            }

            // Notify branch admin
            if ($rule->notify_branch_admin) {
                $this->notifications->writeToRole(
                    roleName: 'branch_admin',
                    branchId: $rule->branch_id,
                    type: 'urgent_followup',
                    title: $title,
                    body: $body,
                    severity: $severity,
                    channel: $rule->preferred_channel,
                    relatedType: 'follow_up',
                    relatedId: $row['follow_up_id'] ?? null,
                    data: ['rule_id' => $rule->id, 'patient_id' => $row['patient_id']],
                );
                $written++;
            }

            // Notify doctor for critical
            if ($rule->notify_doctor && $rule->priority === 'critical') {
                $this->notifications->writeToRole(
                    roleName: 'doctor',
                    branchId: $rule->branch_id,
                    type: 'urgent_followup',
                    title: $title,
                    body: $body,
                    severity: 'critical',
                    channel: 'line',
                    relatedType: 'follow_up',
                    relatedId: $row['follow_up_id'] ?? null,
                    data: ['rule_id' => $rule->id, 'patient_id' => $row['patient_id']],
                );
                $written++;
            }
        }

        return $written;
    }

    /**
     * @return array<int, array{patient_id:int, patient_name:string, reason:string, follow_up_id?:int}>
     */
    private function resolveCandidates(FollowUpRule $rule): array
    {
        $value = (array) $rule->condition_value;
        $today = Carbon::today();

        switch ($rule->condition_type) {
            case 'overdue_days':
                $days = (int) ($value['days'] ?? 3);
                $threshold = $today->copy()->subDays($days);

                return FollowUp::query()
                    ->where('branch_id', $rule->branch_id)
                    ->where('status', 'pending')
                    ->whereDate('follow_up_date', '<', $threshold->toDateString())
                    ->with('patient:id,first_name,last_name,hn')
                    ->limit(200)
                    ->get()
                    ->map(fn ($f) => [
                        'patient_id' => $f->patient_id,
                        'patient_name' => trim(($f->patient?->first_name ?? '').' '.($f->patient?->last_name ?? '')),
                        'reason' => "เลยกำหนดนัด ({$f->follow_up_date->toDateString()}) มากกว่า {$days} วัน",
                        'follow_up_id' => $f->id,
                    ])
                    ->toArray();

            case 'vip_overdue_days':
                $days = (int) ($value['days'] ?? 7);
                $threshold = $today->copy()->subDays($days);

                return FollowUp::query()
                    ->where('branch_id', $rule->branch_id)
                    ->where('status', 'pending')
                    ->whereDate('follow_up_date', '<', $threshold->toDateString())
                    ->whereHas('patient.customerGroup', fn ($q) => $q->where('name', 'VIP'))
                    ->with('patient:id,first_name,last_name,hn,customer_group_id')
                    ->limit(200)
                    ->get()
                    ->map(fn ($f) => [
                        'patient_id' => $f->patient_id,
                        'patient_name' => trim(($f->patient?->first_name ?? '').' '.($f->patient?->last_name ?? '')),
                        'reason' => "VIP เลยนัด {$f->follow_up_date->toDateString()} มากกว่า {$days} วัน",
                        'follow_up_id' => $f->id,
                    ])
                    ->toArray();

            case 'course_expiring_days':
                $days = (int) ($value['days'] ?? 14);
                $threshold = $today->copy()->addDays($days);

                return Course::query()
                    ->where('branch_id', $rule->branch_id)
                    ->where('status', 'active')
                    ->where('remaining_sessions', '>', 0)
                    ->whereDate('expires_at', '<=', $threshold->toDateString())
                    ->whereDate('expires_at', '>=', $today->toDateString())
                    ->with('patient:id,first_name,last_name,hn')
                    ->limit(200)
                    ->get()
                    ->map(fn ($c) => [
                        'patient_id' => $c->patient_id,
                        'patient_name' => trim(($c->patient?->first_name ?? '').' '.($c->patient?->last_name ?? '')),
                        'reason' => "คอร์ส '{$c->name}' เหลือ {$c->remaining_sessions} session • หมดอายุ {$c->expires_at->toDateString()}",
                    ])
                    ->toArray();

            case 'wallet_low_amount':
                $threshold = (float) ($value['amount'] ?? 1000);

                return MemberAccount::query()
                    ->where('branch_id', $rule->branch_id)
                    ->where('status', 'active')
                    ->where('balance', '<=', $threshold)
                    ->where('balance', '>', 0)
                    ->with('patient:id,first_name,last_name,hn')
                    ->limit(200)
                    ->get()
                    ->map(fn ($a) => [
                        'patient_id' => $a->patient_id,
                        'patient_name' => trim(($a->patient?->first_name ?? '').' '.($a->patient?->last_name ?? '')),
                        'reason' => "ยอด wallet ต่ำ {$a->balance} บาท",
                    ])
                    ->toArray();

            case 'dormant_days':
                $days = (int) ($value['days'] ?? 90);
                $threshold = $today->copy()->subDays($days);

                return Patient::query()
                    ->where('branch_id', $rule->branch_id)
                    ->where(function ($q) use ($threshold) {
                        $q->whereDate('last_visit_at', '<', $threshold->toDateString())
                            ->orWhereNull('last_visit_at');
                    })
                    ->whereNotNull('last_visit_at') // exclude never-visited
                    ->limit(200)
                    ->get()
                    ->map(fn ($p) => [
                        'patient_id' => $p->id,
                        'patient_name' => trim(($p->first_name ?? '').' '.($p->last_name ?? '')),
                        'reason' => "ไม่มาคลินิกตั้งแต่ {$p->last_visit_at?->toDateString()} ({$days}+ วัน)",
                    ])
                    ->toArray();

            default:
                return [];
        }
    }
}
