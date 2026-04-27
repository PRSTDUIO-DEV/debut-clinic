<?php

namespace App\Services;

use App\Models\BirthdayCampaign;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class BirthdayCampaignService
{
    public const OFFSETS = ['30', '7', '0', '+3'];

    public function __construct(private NotificationService $notifications) {}

    /**
     * Run all active campaigns for a given date (default today).
     * Returns total notifications written across all campaigns.
     */
    public function runAll(?string $date = null, ?int $branchId = null): int
    {
        $today = $date ? Carbon::parse($date)->startOfDay() : Carbon::today();
        $q = BirthdayCampaign::query()->where('is_active', true);
        if ($branchId) {
            $q->where('branch_id', $branchId);
        }

        $total = 0;
        foreach ($q->get() as $campaign) {
            $total += $this->runCampaign($campaign, $today);
        }

        return $total;
    }

    public function runCampaign(BirthdayCampaign $campaign, ?Carbon $today = null): int
    {
        $today = $today ?? Carbon::today();

        // Idempotent: skip if already run today
        if ($campaign->last_run_at && $campaign->last_run_at->isSameDay($today)) {
            return 0;
        }

        $written = 0;
        $templates = (array) $campaign->templates;

        foreach (self::OFFSETS as $offset) {
            $tpl = $templates[$offset] ?? null;
            if (! $tpl) {
                continue;
            }
            $patients = $this->patientsAtOffset($campaign->branch_id, $today, $offset);
            foreach ($patients as $p) {
                $title = $this->render($tpl['title'] ?? 'แจ้งวันเกิด', $p);
                $body = $this->render($tpl['body'] ?? '', $p);
                $channel = $tpl['channel'] ?? 'in_app';

                $this->notifications->write(
                    recipientType: 'patient',
                    recipientId: $p->id,
                    type: 'birthday',
                    title: $title,
                    body: $body,
                    severity: 'info',
                    channel: $channel,
                    relatedType: 'birthday_campaign',
                    relatedId: $campaign->id,
                    branchId: $campaign->branch_id,
                    data: ['offset_days' => $offset],
                );
                $written++;

                // Also notify branch admin for "+3" follow-up reminder
                if ($offset === '+3') {
                    $this->notifications->writeToRole(
                        roleName: 'branch_admin',
                        branchId: $campaign->branch_id,
                        type: 'birthday_followup',
                        title: 'ติดตามผู้ป่วยที่เพิ่งครบรอบวันเกิด',
                        body: "ติดตาม {$p->first_name} {$p->last_name} (HN {$p->hn}) — ใช้คูปองวันเกิดหรือยัง?",
                        severity: 'warning',
                        channel: 'in_app',
                        relatedType: 'patient',
                        relatedId: $p->id,
                        data: ['offset_days' => '+3'],
                    );
                }
            }
        }

        $campaign->last_run_at = $today;
        $campaign->total_sent = (int) $campaign->total_sent + $written;
        $campaign->save();

        return $written;
    }

    /**
     * Patients whose birthday falls at the given offset relative to today.
     * Compares only month-day (year ignored).
     */
    private function patientsAtOffset(int $branchId, Carbon $today, string $offset): Collection
    {
        $days = (int) ltrim($offset, '+');
        $target = $offset === '+3'
            ? $today->copy()->subDays($days)
            : $today->copy()->addDays($days);

        return Patient::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', $target->month)
            ->whereDay('date_of_birth', $target->day)
            ->get();
    }

    private function render(string $template, Patient $p): string
    {
        $vars = [
            'first_name' => $p->first_name,
            'last_name' => $p->last_name,
            'nickname' => $p->nickname ?: ($p->first_name ?? ''),
            'hn' => $p->hn,
            'phone' => $p->phone,
        ];

        return preg_replace_callback('/\{\{\s*([\w_]+)\s*\}\}/', function ($m) use ($vars) {
            return $vars[$m[1]] ?? $m[0];
        }, $template);
    }
}
