<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Services\PhotoUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PhotoController extends Controller
{
    public function __construct(private PhotoUploadService $photos) {}

    public function store(Request $request, string $patientUuid): JsonResponse
    {
        $branchId = (int) app('branch.id');
        $patient = Patient::query()
            ->where('uuid', $patientUuid)
            ->where('branch_id', $branchId)
            ->firstOrFail();

        $data = $request->validate([
            'file' => ['required', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp'],
            'type' => ['nullable', Rule::in(['before', 'after', 'general'])],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'taken_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $photo = $this->photos->upload(
            patient: $patient,
            file: $request->file('file'),
            type: $data['type'] ?? 'general',
            visitId: $data['visit_id'] ?? null,
            user: $request->user(),
            notes: $data['notes'] ?? null,
            takenAt: $data['taken_at'] ?? null,
        );

        return response()->json(['data' => $this->present($photo)], 201);
    }

    public function destroy(PatientPhoto $photo): JsonResponse
    {
        $branchId = (int) app('branch.id');
        if ($photo->branch_id !== $branchId) {
            return response()->json(['message' => 'not found'], 404);
        }
        $this->photos->delete($photo);

        return response()->json(null, 204);
    }

    private function present(PatientPhoto $p): array
    {
        return [
            'id' => $p->id,
            'type' => $p->type,
            'file_path' => $p->file_path,
            'thumbnail_path' => $p->thumbnail_path,
            'url' => $p->url(),
            'thumbnail_url' => $p->thumbnailUrl(),
            'width' => $p->width,
            'height' => $p->height,
            'mime_type' => $p->mime_type,
            'file_size' => $p->file_size,
            'taken_at' => optional($p->taken_at)->toIso8601String(),
            'notes' => $p->notes,
        ];
    }
}
