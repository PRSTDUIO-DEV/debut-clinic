<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpdCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function bootUser(): array
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        $user->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($user);

        return [$user, $branch];
    }

    public function test_visits_endpoint_returns_paginated(): void
    {
        [, $branch] = $this->bootUser();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $this->getJson("/api/v1/patients/{$patient->uuid}/visits", ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'last_page']]);
    }

    public function test_financial_endpoint_returns_aggregated_stats(): void
    {
        [, $branch] = $this->bootUser();
        $patient = Patient::factory()->create(['branch_id' => $branch->id, 'total_spent' => 5000, 'visit_count' => 3]);

        $res = $this->getJson("/api/v1/patients/{$patient->uuid}/financial", ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertEquals(5000, (float) $res->json('data.total_spent'));
        $this->assertSame(3, $res->json('data.visit_count'));
    }

    public function test_lab_results_returns_empty_for_now(): void
    {
        [, $branch] = $this->bootUser();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $this->getJson("/api/v1/patients/{$patient->uuid}/lab-results", ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
