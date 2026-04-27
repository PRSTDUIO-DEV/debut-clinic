<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LabApiTest extends TestCase
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

    public function test_test_catalog_crud(): void
    {
        [$branch] = $this->bootAdmin();

        $created = $this->postJson('/api/v1/lab-tests', [
            'code' => 'FBS', 'name' => 'Fasting Blood Sugar',
            'unit' => 'mg/dL', 'ref_min' => 70, 'ref_max' => 110, 'price' => 80,
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $id = $created->json('data.id');
        $this->putJson("/api/v1/lab-tests/$id", ['name' => 'FBS Updated'], ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.name', 'FBS Updated');

        $this->getJson('/api/v1/lab-tests', ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson("/api/v1/lab-tests/$id", [], ['X-Branch-Id' => $branch->id])->assertNoContent();
    }

    public function test_create_order_then_record_results_flags_correctly(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $fbs = LabTest::create([
            'branch_id' => $branch->id, 'code' => 'FBS', 'name' => 'FBS',
            'unit' => 'mg/dL', 'ref_min' => 70, 'ref_max' => 110, 'price' => 80, 'is_active' => true,
        ]);

        $created = $this->postJson('/api/v1/lab-orders', [
            'patient_uuid' => $patient->uuid,
            'test_ids' => [$fbs->id],
            'status' => 'sent',
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $orderId = $created->json('data.id');

        $res = $this->postJson("/api/v1/lab-orders/{$orderId}/results", [
            'rows' => [['lab_test_id' => $fbs->id, 'value_numeric' => 200]],
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $row = collect($res->json('data.rows'))->first();
        $this->assertSame('high', $row['abnormal_flag']);
        $this->assertSame('completed', $res->json('data.status'));
    }

    public function test_attach_report_uploads_pdf(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $fbs = LabTest::create([
            'branch_id' => $branch->id, 'code' => 'FBS', 'name' => 'FBS',
            'price' => 80, 'is_active' => true,
        ]);
        $created = $this->postJson('/api/v1/lab-orders', [
            'patient_uuid' => $patient->uuid,
            'test_ids' => [$fbs->id],
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $orderId = $created->json('data.id');

        $pdf = UploadedFile::fake()->create('report.pdf', 200, 'application/pdf');
        $this->post("/api/v1/lab-orders/{$orderId}/report", [
            'file' => $pdf,
        ], ['X-Branch-Id' => $branch->id, 'Accept' => 'application/json'])->assertOk();

        $order = LabOrder::query()->findOrFail($orderId);
        Storage::disk('public')->assertExists($order->report_path);
    }

    public function test_by_patient_endpoint_returns_orders_with_rows(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $cbc = LabTest::create([
            'branch_id' => $branch->id, 'code' => 'CBC', 'name' => 'CBC',
            'unit' => '×10^9/L', 'ref_min' => 4, 'ref_max' => 10, 'price' => 250, 'is_active' => true,
        ]);
        $created = $this->postJson('/api/v1/lab-orders', [
            'patient_uuid' => $patient->uuid,
            'test_ids' => [$cbc->id],
        ], ['X-Branch-Id' => $branch->id])->assertCreated();
        $orderId = $created->json('data.id');
        $this->postJson("/api/v1/lab-orders/{$orderId}/results", [
            'rows' => [['lab_test_id' => $cbc->id, 'value_numeric' => 6.0]],
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $res = $this->getJson("/api/v1/patients/{$patient->uuid}/lab-orders", ['X-Branch-Id' => $branch->id])->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('normal', $res->json('data.0.rows.0.abnormal_flag'));
    }
}
