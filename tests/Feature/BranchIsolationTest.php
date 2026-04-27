<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_scope_filters_by_branch_id_when_context_is_set(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        // Bypass scope to seed both sides
        User::factory()->count(3)->create(['branch_id' => $branchA->id]);
        User::factory()->count(2)->create(['branch_id' => $branchB->id]);

        // Apply branch context A
        $this->app->instance('branch.id', $branchA->id);
        $countA = User::query()->count();

        // Switch context
        $this->app->instance('branch.id', $branchB->id);
        $countB = User::query()->count();

        $this->assertSame(3, $countA);
        $this->assertSame(2, $countB);
    }

    public function test_no_branch_context_returns_all_records(): void
    {
        $branchA = Branch::factory()->create();
        User::factory()->count(2)->create(['branch_id' => $branchA->id]);

        // Without binding branch.id the scope should not filter
        $this->assertSame(2, User::query()->count());
    }
}
