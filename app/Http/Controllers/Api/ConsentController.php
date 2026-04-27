<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsentTemplate;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConsentController extends Controller
{
    public function __construct(private ConsentService $consents) {}

    public function templates(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $rows = ConsentTemplate::query()
            ->where('branch_id', $branchId)
            ->when($request->boolean('only_active', true), fn ($q) => $q->where('is_active', true))
            ->orderBy('title')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('consent_templates')->where(fn ($q) => $q->where('branch_id', $branchId))],
            'title' => ['required', 'string', 'max:200'],
            'body_html' => ['nullable', 'string'],
            'validity_days' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'require_signature' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['branch_id'] = $branchId;
        $data['validity_days'] ??= 365;
        $data['require_signature'] ??= true;
        $data['is_active'] ??= true;

        $template = ConsentTemplate::create($data);

        return response()->json(['data' => $template], 201);
    }

    public function updateTemplate(Request $request, ConsentTemplate $template): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($template->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'body_html' => ['nullable', 'string'],
            'validity_days' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'require_signature' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $template->fill($data)->save();

        return response()->json(['data' => $template]);
    }

    public function destroyTemplate(ConsentTemplate $template): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($template->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $template->delete();

        return response()->json(null, 204);
    }

    public function createForPatient(Request $request, string $patientUuid): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $patient = Patient::query()
            ->where('uuid', $patientUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $data = $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:consent_templates,id'],
            'name' => ['required_without:template_id', 'string', 'max:200'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['template_id'])) {
            $template = ConsentTemplate::query()
                ->where('id', $data['template_id'])
                ->where('branch_id', $branchId)
                ->firstOrFail();
            $consent = $this->consents->createFromTemplate(
                $template, $patient, $request->user(), $data['expires_at'] ?? null,
            );
            if (! empty($data['notes'])) {
                $consent->notes = $data['notes'];
                $consent->save();
            }
        } else {
            $consent = PatientConsent::create([
                'branch_id' => $branchId,
                'patient_id' => $patient->id,
                'name' => $data['name'],
                'status' => 'pending',
                'expires_at' => $data['expires_at'] ?? null,
                'uploaded_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        return response()->json(['data' => $this->present($consent)], 201);
    }

    public function sign(Request $request, PatientConsent $consent): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($consent->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate([
            'signed_by_name' => ['required', 'string', 'max:150'],
            'signature' => ['required', 'string'],
        ]);
        if (strlen($data['signature']) < 100) {
            throw ValidationException::withMessages(['signature' => 'ลายเซ็นสั้นเกินไป']);
        }

        $consent = $this->consents->sign($consent, $data['signed_by_name'], $data['signature'], $request->user());

        return response()->json(['data' => $this->present($consent)]);
    }

    public function void(Request $request, PatientConsent $consent): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($consent->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->consents->void($consent, $data['reason']);

        return response()->json(['data' => $this->present($consent->fresh())]);
    }

    private function present(PatientConsent $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'template_id' => $c->template_id,
            'status' => $c->status,
            'signed_by_name' => $c->signed_by_name,
            'signed_at' => optional($c->signed_at)->toIso8601String(),
            'expires_at' => optional($c->expires_at)->toDateString(),
            'file_url' => $c->fileUrl(),
            'signature_url' => $c->signatureUrl(),
            'notes' => $c->notes,
        ];
    }
}
