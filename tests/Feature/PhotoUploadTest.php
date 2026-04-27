<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('public');
    }

    private function bootAdmin(): array
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->branches()->attach($branch->id, ['is_primary' => true]);
        $admin->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($admin);

        return [$branch, $admin];
    }

    public function test_upload_stores_file_and_creates_record(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $file = UploadedFile::fake()->image('before.jpg', 800, 600);

        $res = $this->post("/api/v1/patients/{$patient->uuid}/photos", [
            'file' => $file,
            'type' => 'before',
            'notes' => 'demo',
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $photoId = $res->json('data.id');
        $photo = PatientPhoto::query()->findOrFail($photoId);

        Storage::disk('public')->assertExists($photo->file_path);
        Storage::disk('public')->assertExists($photo->thumbnail_path);
        $this->assertSame('image/jpeg', $photo->mime_type);
        $this->assertSame(800, $photo->width);
        $this->assertSame(600, $photo->height);
        $this->assertSame('before', $photo->type);
    }

    public function test_upload_rejects_non_image(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $bogus = File::create('virus.exe', 10);

        $this->post("/api/v1/patients/{$patient->uuid}/photos", [
            'file' => $bogus,
        ], ['X-Branch-Id' => $branch->id, 'Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_destroy_soft_deletes_photo(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $file = UploadedFile::fake()->image('a.jpg', 400, 400);
        $res = $this->post("/api/v1/patients/{$patient->uuid}/photos", [
            'file' => $file,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $id = $res->json('data.id');

        $this->delete("/api/v1/photos/{$id}", [], ['X-Branch-Id' => $branch->id])->assertNoContent();
        $this->assertSoftDeleted('patient_photos', ['id' => $id]);
    }
}
