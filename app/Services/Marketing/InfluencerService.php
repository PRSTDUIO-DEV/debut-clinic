<?php

namespace App\Services\Marketing;

use App\Models\InfluencerCampaign;
use App\Models\InfluencerReferral;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class InfluencerService
{
    public function generateShortcode(): string
    {
        do {
            $code = Str::lower(Str::random(6));
        } while (InfluencerCampaign::where('shortcode', $code)->exists());

        return $code;
    }

    /**
     * Track a click on the landing URL — produces a referral row (patient_id null until signup).
     */
    public function trackClick(InfluencerCampaign $campaign, ?string $ip = null, ?string $ua = null): InfluencerReferral
    {
        return InfluencerReferral::create([
            'campaign_id' => $campaign->id,
            'patient_id' => null,
            'ip' => $ip,
            'user_agent' => $ua ? Str::limit($ua, 500, '') : null,
            'referred_at' => now(),
        ]);
    }

    /**
     * Attach a patient to an unattached referral (or create a new referral row).
     */
    public function attachPatient(InfluencerCampaign $campaign, Patient $patient, ?int $referralId = null): InfluencerReferral
    {
        if ($referralId) {
            $r = InfluencerReferral::find($referralId);
            if ($r && $r->campaign_id === $campaign->id && ! $r->patient_id) {
                $r->patient_id = $patient->id;
                $r->first_visit_at = $patient->last_visit_at ?: $r->first_visit_at;
                $r->save();

                return $r;
            }
        }

        return InfluencerReferral::create([
            'campaign_id' => $campaign->id,
            'patient_id' => $patient->id,
            'referred_at' => now(),
            'first_visit_at' => $patient->last_visit_at,
            'lifetime_value' => $patient->total_spent ?? 0,
        ]);
    }

    /**
     * Recompute lifetime_value for all referrals in a campaign.
     */
    public function recomputeLtv(InfluencerCampaign $campaign): int
    {
        $rows = InfluencerReferral::where('campaign_id', $campaign->id)
            ->whereNotNull('patient_id')
            ->get();

        $count = 0;
        foreach ($rows as $r) {
            $patient = Patient::find($r->patient_id);
            if (! $patient) {
                continue;
            }
            $r->lifetime_value = (float) $patient->total_spent;
            $r->first_visit_at = $r->first_visit_at ?: $patient->last_visit_at;
            $r->save();
            $count++;
        }

        return $count;
    }

    /**
     * @return array{
     *     campaign: InfluencerCampaign,
     *     clicks: int,
     *     signups: int,
     *     ltv_total: float,
     *     ltv_avg: float,
     *     budget: float,
     *     roi_pct: float|null,
     *     duration_days: int,
     * }
     */
    public function report(InfluencerCampaign $campaign): array
    {
        $clicks = InfluencerReferral::where('campaign_id', $campaign->id)->count();
        $signups = InfluencerReferral::where('campaign_id', $campaign->id)
            ->whereNotNull('patient_id')->count();
        $ltvTotal = (float) InfluencerReferral::where('campaign_id', $campaign->id)->sum('lifetime_value');
        $ltvAvg = $signups > 0 ? round($ltvTotal / $signups, 2) : 0;
        $budget = (float) $campaign->total_budget;
        $roi = $budget > 0 ? round((($ltvTotal - $budget) / $budget) * 100, 2) : null;

        return [
            'campaign' => $campaign,
            'clicks' => $clicks,
            'signups' => $signups,
            'ltv_total' => round($ltvTotal, 2),
            'ltv_avg' => $ltvAvg,
            'budget' => $budget,
            'roi_pct' => $roi,
            'duration_days' => Carbon::parse($campaign->start_date)->diffInDays(Carbon::parse($campaign->end_date)) + 1,
        ];
    }
}
