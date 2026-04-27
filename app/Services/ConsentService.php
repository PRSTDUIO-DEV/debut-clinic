<?php

namespace App\Services;

use App\Models\ConsentTemplate;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConsentService
{
    public function __construct(private string $disk = 'public') {}

    /**
     * Create a pending PatientConsent from a template.
     */
    public function createFromTemplate(
        ConsentTemplate $template,
        Patient $patient,
        ?User $user = null,
        ?string $customExpiresAt = null,
    ): PatientConsent {
        if ($template->branch_id !== $patient->branch_id) {
            throw ValidationException::withMessages(['template' => 'Template ไม่อยู่ในสาขาเดียวกับผู้ป่วย']);
        }

        $expires = $customExpiresAt ?? ($template->validity_days > 0
            ? now()->addDays($template->validity_days)->toDateString()
            : null);

        return PatientConsent::create([
            'branch_id' => $patient->branch_id,
            'patient_id' => $patient->id,
            'template_id' => $template->id,
            'name' => $template->title,
            'status' => 'pending',
            'expires_at' => $expires,
            'uploaded_by' => $user?->id,
        ]);
    }

    /**
     * Sign a consent. $signatureDataUrl is a "data:image/png;base64,..." string.
     */
    public function sign(
        PatientConsent $consent,
        string $signedByName,
        string $signatureDataUrl,
        ?User $user = null,
    ): PatientConsent {
        if ($consent->status === 'signed') {
            throw ValidationException::withMessages(['consent' => 'เอกสารนี้เซ็นไปแล้ว']);
        }

        $png = $this->decodeDataUrl($signatureDataUrl);
        $relPath = sprintf(
            'consents/signatures/%d/%d/%s.png',
            $consent->branch_id, $consent->patient_id, Str::uuid(),
        );
        Storage::disk($this->disk)->put($relPath, $png);

        $consent->signature_path = $relPath;
        $consent->signed_by_name = $signedByName;
        $consent->signed_at = now();
        $consent->status = 'signed';
        if ($user) {
            $consent->uploaded_by = $consent->uploaded_by ?? $user->id;
        }
        $consent->save();

        return $consent;
    }

    public function void(PatientConsent $consent, string $reason): PatientConsent
    {
        if ($consent->status === 'expired') {
            throw ValidationException::withMessages(['consent' => 'เอกสารหมดอายุไปแล้ว']);
        }
        $consent->status = 'expired';
        $consent->notes = trim(($consent->notes ? $consent->notes."\n" : '').'[VOID] '.$reason);
        $consent->save();

        return $consent;
    }

    public function expireExpired(?int $branchId = null): int
    {
        $q = PatientConsent::query()
            ->where('status', 'signed')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString());
        if ($branchId) {
            $q->where('branch_id', $branchId);
        }

        return $q->update(['status' => 'expired']);
    }

    private function decodeDataUrl(string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, 'data:image/')) {
            throw ValidationException::withMessages(['signature' => 'รูปแบบลายเซ็นไม่ถูกต้อง']);
        }
        $commaPos = strpos($dataUrl, ',');
        if ($commaPos === false) {
            throw ValidationException::withMessages(['signature' => 'รูปแบบลายเซ็นไม่ถูกต้อง']);
        }
        $b64 = substr($dataUrl, $commaPos + 1);
        $bin = base64_decode($b64, true);
        if ($bin === false) {
            throw ValidationException::withMessages(['signature' => 'ถอดรหัสลายเซ็นไม่สำเร็จ']);
        }

        return $bin;
    }
}
