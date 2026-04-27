<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessagingProvider;
use App\Models\Patient;
use App\Models\Scopes\BranchScope;
use App\Services\Messaging\LineMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LiffController extends Controller
{
    public function __construct(private LineMessagingService $line) {}

    /**
     * Link a LINE userId to an existing patient via HN/phone match.
     * Body: { id_token, hn?, phone? }
     */
    public function linkPatient(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'hn' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
        ]);
        if (empty($data['hn']) && empty($data['phone'])) {
            return response()->json(['ok' => false, 'error' => 'ต้องระบุ HN หรือเบอร์โทร'], 422);
        }

        // Verify LIFF id_token via LINE — needs LIFF channel id from a provider config (any branch)
        $verified = $this->verifyLiffToken($data['id_token']);
        if (! $verified) {
            return response()->json(['ok' => false, 'error' => 'invalid id_token'], 401);
        }

        $userId = $verified['sub'] ?? null;
        if (! $userId) {
            return response()->json(['ok' => false, 'error' => 'no sub in token'], 422);
        }

        $patientQ = Patient::query();
        if (! empty($data['hn'])) {
            $patientQ->where('hn', $data['hn']);
        }
        if (! empty($data['phone'])) {
            $patientQ->where('phone', $data['phone']);
        }
        $patient = $patientQ->first();
        if (! $patient) {
            return response()->json(['ok' => false, 'error' => 'patient not found'], 404);
        }

        // Unlink any other patient holding this user_id
        Patient::query()
            ->where('line_user_id', $userId)
            ->where('id', '!=', $patient->id)
            ->update(['line_user_id' => null, 'line_linked_at' => null]);

        $patient->line_user_id = $userId;
        $patient->line_linked_at = now();
        if (! $patient->line_id && ! empty($verified['name'])) {
            $patient->line_id = $verified['name'];
        }
        $patient->save();

        // Send a confirmation push if any LINE provider is active for this branch
        $provider = MessagingProvider::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('branch_id', $patient->branch_id)
            ->where('type', 'line')
            ->where('is_active', true)
            ->first();
        if ($provider) {
            $this->line->pushText(
                $provider,
                $userId,
                "เชื่อมบัญชีสำเร็จ ✅\nคุณ {$patient->first_name} {$patient->last_name} (HN {$patient->hn})",
                'liff_link',
                $patient->id,
            );
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'patient_uuid' => $patient->uuid,
                'hn' => $patient->hn,
                'name' => trim(($patient->first_name ?? '').' '.($patient->last_name ?? '')),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $data = $request->validate(['id_token' => ['required', 'string']]);
        $verified = $this->verifyLiffToken($data['id_token']);
        if (! $verified) {
            return response()->json(['ok' => false, 'error' => 'invalid id_token'], 401);
        }
        $userId = $verified['sub'] ?? null;
        $patient = $userId ? Patient::query()->where('line_user_id', $userId)->first() : null;

        return response()->json([
            'ok' => true,
            'data' => [
                'line' => $verified,
                'patient' => $patient ? [
                    'uuid' => $patient->uuid,
                    'hn' => $patient->hn,
                    'name' => trim(($patient->first_name ?? '').' '.($patient->last_name ?? '')),
                    'phone' => $patient->phone,
                ] : null,
            ],
        ]);
    }

    /**
     * Verify a LIFF id_token via LINE OAuth verify endpoint.
     * Returns decoded payload or null.
     */
    private function verifyLiffToken(string $idToken): ?array
    {
        // Dev fallback: accept "dev:USERID:NAME" tokens in local/non-production environments,
        // regardless of whether a LINE provider has been configured. This lets developers
        // exercise the link flow without owning a real LIFF channel.
        if (str_starts_with($idToken, 'dev:') && app()->environment(['local', 'testing', 'staging'])) {
            $parts = explode(':', $idToken, 3);

            return ['sub' => $parts[1] ?? '', 'name' => $parts[2] ?? ''];
        }

        // Find any active LINE provider with channel_id (LIFF channel id)
        $provider = MessagingProvider::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('type', 'line')
            ->where('is_active', true)
            ->first();
        $clientId = $provider?->configArray()['channel_id'] ?? null;
        if (! $clientId) {
            return null;
        }

        $resp = Http::asForm()->timeout(8)->post('https://api.line.me/oauth2/v2.1/verify', [
            'id_token' => $idToken,
            'client_id' => $clientId,
        ]);
        if ($resp->successful()) {
            return $resp->json();
        }

        return null;
    }
}
