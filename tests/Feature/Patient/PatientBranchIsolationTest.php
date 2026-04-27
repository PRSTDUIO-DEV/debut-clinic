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

class PatientBranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_patient_from_other_branch_is_not_visible(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        Patient::factory()->count(3)->create(['branch_id' => $branchA->id]);
        $bPatient = Patient::factory()->create(['branch_id' => $branchB->id]);

        $user = User::factory()->create(['branch_id' => $branchA->id]);
        $user->branches()->attach($branchA->id, ['is_primary' => true]);
        $user->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($user);

        // List under branch A only sees A
        $res = $this->getJson('/api/v1/patients?per_page=20', ['X-Branch-Id' => $branchA->id]);
        $res->assertOk()->assertJsonPath('meta.total', 3);

        // Try to access branch B's patient under branch A context
        $this->getJson('/api/v1/patients/'.$bPatient->uuid, ['X-Branch-Id' => $branchA->id])
            ->assertNotFound();
    }
}
