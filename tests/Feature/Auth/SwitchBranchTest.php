<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SwitchBranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_switch_to_authorized_branch_succeeds(): void
    {
        $b1 = Branch::factory()->create();
        $b2 = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $b1->id]);
        $user->branches()->attach([$b1->id => ['is_primary' => true], $b2->id => ['is_primary' => false]]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/switch-branch', ['branch_id' => $b2->id])
            ->assertOk()
            ->assertJsonPath('data.active_branch_id', $b2->id);
    }

    public function test_switch_to_unauthorized_branch_is_forbidden(): void
    {
        $b1 = Branch::factory()->create();
        $b2 = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $b1->id]);
        $user->branches()->attach($b1->id, ['is_primary' => true]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/switch-branch', ['branch_id' => $b2->id])
            ->assertStatus(403)
            ->assertJsonPath('code', 'branch_not_authorized');
    }
}
