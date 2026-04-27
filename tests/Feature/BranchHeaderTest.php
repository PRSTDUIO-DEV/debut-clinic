<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_branch_header_returns_400(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/ping')
            ->assertStatus(400)
            ->assertJsonPath('code', 'branch_header_missing');
    }

    public function test_unauthorized_branch_returns_403(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branchA->id]);
        $user->branches()->attach($branchA->id, ['is_primary' => true]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/ping', ['X-Branch-Id' => $branchB->id])
            ->assertStatus(403)
            ->assertJsonPath('code', 'branch_not_authorized');
    }

    public function test_authorized_branch_passes_through(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/ping', ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.branch', $branch->id);
    }
}
