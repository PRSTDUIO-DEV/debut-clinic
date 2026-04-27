<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PhotoUploadService
{
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public const MAX_BYTES = 8 * 1024 * 1024; // 8 MB

    public const THUMB_SIZE = 256;

    public function __construct(private string $disk = 'public') {}

    public function upload(
        Patient $patient,
        UploadedFile $file,
        string $type = 'general',
        ?int $visitId = null,
        ?User $user = null,
        ?string $notes = null,
        ?string $takenAt = null,
    ): PatientPhoto {
        $this->validate($file);

        $ext = strtolower($file->getClientOriginalExtension() ?: $this->extensionFromMime($file->getMimeType()));
        $uuid = (string) Str::uuid();
        $folder = sprintf('photos/%d/%d/%s', $patient->branch_id, $patient->id, now()->format('Ym'));
        $relPath = $folder.'/'.$uuid.'.'.$ext;
        $thumbRel = $folder.'/'.$uuid.'_thumb.jpg';

        Storage::disk($this->disk)->putFileAs($folder, $file, $uuid.'.'.$ext);

        [$width, $height] = $this->dimensions($file->getRealPath());
        $this->makeThumbnail($file->getRealPath(), $thumbRel, $width, $height);

        return PatientPhoto::create([
            'branch_id' => $patient->branch_id,
            'patient_id' => $patient->id,
            'visit_id' => $visitId,
            'type' => $type,
            'file_path' => $relPath,
            'thumbnail_path' => $thumbRel,
            'width' => $width,
            'height' => $height,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'storage_disk' => $this->disk,
            'taken_at' => $takenAt ?? now(),
            'uploaded_by' => $user?->id,
            'notes' => $notes,
        ]);
    }

    public function delete(PatientPhoto $photo, bool $purgeFile = false): void
    {
        $photo->delete();
        if ($purgeFile) {
            $disk = Storage::disk($photo->storage_disk ?: $this->disk);
            $disk->delete([$photo->file_path, $photo->thumbnail_path]);
        }
    }

    private function validate(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'อัปโหลดไม่สำเร็จ']);
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'ไฟล์ใหญ่เกิน 8 MB']);
        }
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['file' => 'ต้องเป็นรูป JPEG/PNG/WebP เท่านั้น']);
        }
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function dimensions(string $path): array
    {
        $info = @getimagesize($path);
        if (! $info) {
            return [0, 0];
        }

        return [(int) $info[0], (int) $info[1]];
    }

    private function makeThumbnail(string $sourcePath, string $thumbRel, int $width, int $height): void
    {
        if ($width === 0 || $height === 0) {
            return;
        }
        $info = @getimagesize($sourcePath);
        if (! $info) {
            return;
        }
        $mime = $info['mime'];
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            default => null,
        };
        if (! $src) {
            return;
        }

        $ratio = $width / $height;
        $tw = self::THUMB_SIZE;
        $th = self::THUMB_SIZE;
        if ($ratio > 1) {
            $th = (int) round(self::THUMB_SIZE / $ratio);
        } else {
            $tw = (int) round(self::THUMB_SIZE * $ratio);
        }

        $thumb = imagecreatetruecolor($tw, $th);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $tw, $th, $width, $height);

        $tmp = tempnam(sys_get_temp_dir(), 'thumb_').'.jpg';
        imagejpeg($thumb, $tmp, 82);
        imagedestroy($src);
        imagedestroy($thumb);

        Storage::disk($this->disk)->put($thumbRel, file_get_contents($tmp));
        @unlink($tmp);
    }

    private function extensionFromMime(?string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
