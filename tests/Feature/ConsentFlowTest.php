<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ConsentTemplate;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\Role;
use App\Models\User;
use App\Services\ConsentService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsentFlowTest extends TestCase
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

    public function test_create_from_template_copies_title_and_validity(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $tpl = ConsentTemplate::create([
            'branch_id' => $branch->id, 'code' => 'PDPA', 'title' => 'PDPA Consent',
            'body_html' => '<p>...</p>', 'validity_days' => 365,
            'require_signature' => true, 'is_active' => true,
        ]);

        $res = $this->postJson("/api/v1/patients/{$patient->uuid}/consents", [
            'template_id' => $tpl->id,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->assertSame('PDPA Consent', $res->json('data.name'));
        $this->assertSame('pending', $res->json('data.status'));
        $this->assertNotNull($res->json('data.expires_at'));
    }

    public function test_create_from_template_rejects_cross_branch(): void
    {
        [$branchA] = $this->bootAdmin();
        $branchB = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branchA->id]);
        $tpl = ConsentTemplate::create([
            'branch_id' => $branchB->id, 'code' => 'PDPA', 'title' => 'PDPA',
            'validity_days' => 365, 'require_signature' => true, 'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->app->make(ConsentService::class)->createFromTemplate(
            $tpl, $patient,
        );
    }

    public function test_sign_endpoint_saves_signature_and_marks_signed(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $consent = PatientConsent::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'name' => 'PDPA', 'status' => 'pending',
        ]);

        // 1x1 transparent PNG base64 (longer than 100 chars after signing in real life;
        // we pad with extra base64 to clear validation length check)
        $b64 = base64_encode(str_repeat('A', 200));
        $dataUrl = 'data:image/png;base64,'.$b64;

        $this->postJson("/api/v1/consents/{$consent->id}/sign", [
            'signed_by_name' => 'สมชาย ใจดี',
            'signature' => $dataUrl,
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $consent->refresh();
        $this->assertSame('signed', $consent->status);
        $this->assertNotNull($consent->signed_at);
        $this->assertSame('สมชาย ใจดี', $consent->signed_by_name);
        Storage::disk('public')->assertExists($consent->signature_path);
    }

    public function test_void_endpoint_marks_expired_with_reason_in_notes(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $consent = PatientConsent::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'name' => 'PDPA', 'status' => 'pending',
        ]);

        $this->postJson("/api/v1/consents/{$consent->id}/void", [
            'reason' => 'ลูกค้าขอยกเลิก',
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $consent->refresh();
        $this->assertSame('expired', $consent->status);
        $this->assertStringContainsString('ลูกค้าขอยกเลิก', (string) $consent->notes);
    }

    public function test_template_crud_endpoints(): void
    {
        [$branch] = $this->bootAdmin();

        // create
        $created = $this->postJson('/api/v1/consent-templates', [
            'code' => 'BTX',
            'title' => 'Botox Consent',
            'validity_days' => 180,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $id = $created->json('data.id');

        // update
        $this->putJson("/api/v1/consent-templates/{$id}", [
            'title' => 'Botox Consent (v2)',
        ], ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.title', 'Botox Consent (v2)');

        // index
        $list = $this->getJson('/api/v1/consent-templates', ['X-Branch-Id' => $branch->id])->assertOk();
        $this->assertCount(1, $list->json('data'));

        // delete
        $this->deleteJson("/api/v1/consent-templates/{$id}", [], ['X-Branch-Id' => $branch->id])->assertNoContent();
    }
}
