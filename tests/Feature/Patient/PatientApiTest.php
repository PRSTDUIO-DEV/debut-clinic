<?php

namespace Tests\Feature\Patient;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    /** create authenticated super_admin user with active branch */
    private function bootUser(?Branch $branch = null): array
    {
        $branch = $branch ?? Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        $user->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($user);

        return [$user, $branch];
    }

    public function test_create_patient_generates_hn_and_returns_201(): void
    {
        [$user, $branch] = $this->bootUser();

        $res = $this->postJson('/api/v1/patients', [
            'first_name' => 'สมชาย',
            'last_name' => 'ใจดี',
            'gender' => 'male',
            'phone' => '081-111-1111',
            'date_of_birth' => '1990-01-01',
        ], ['X-Branch-Id' => $branch->id]);

        $res->assertStatus(201)
            ->assertJsonPath('data.first_name', 'สมชาย')
            ->assertJsonStructure(['data' => ['hn', 'id']]);

        $hn = $res->json('data.hn');
        $this->assertMatchesRegularExpression('/^'.$branch->code.'-\d{6}-\d{4}$/', $hn);
    }

    public function test_duplicate_phone_returns_422_with_thai_message(): void
    {
        [, $branch] = $this->bootUser();

        $payload = [
            'first_name' => 'a', 'last_name' => 'b', 'gender' => 'male', 'phone' => '081-222-2222',
        ];

        $this->postJson('/api/v1/patients', $payload, ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $this->postJson('/api/v1/patients', $payload, ['X-Branch-Id' => $branch->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonFragment(['เบอร์โทรนี้มีในระบบแล้ว']);
    }

    public function test_list_returns_paginated(): void
    {
        [, $branch] = $this->bootUser();

        Patient::factory()->count(25)->create([
            'branch_id' => $branch->id,
        ]);

        $res = $this->getJson('/api/v1/patients?per_page=10', ['X-Branch-Id' => $branch->id]);
        $res->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25);
    }

    public function test_show_finds_patient_by_uuid(): void
    {
        [, $branch] = $this->bootUser();

        $p = Patient::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Find', 'last_name' => 'Me']);

        $this->getJson('/api/v1/patients/'.$p->uuid, ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Find');
    }

    public function test_soft_delete_removes_from_index(): void
    {
        [, $branch] = $this->bootUser();
        $p = Patient::factory()->create(['branch_id' => $branch->id]);

        $this->deleteJson('/api/v1/patients/'.$p->uuid, [], ['X-Branch-Id' => $branch->id])
            ->assertNoContent();

        $this->getJson('/api/v1/patients/'.$p->uuid, ['X-Branch-Id' => $branch->id])
            ->assertNotFound();

        $this->assertDatabaseHas('patients', ['id' => $p->id]); // still in DB (soft delete)
    }
}
