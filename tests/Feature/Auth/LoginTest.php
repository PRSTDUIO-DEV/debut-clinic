<?php

namespace Tests\Feature\Auth;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_login_success_returns_token_and_branches(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);

        $res = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $res->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'is_active'],
                    'branches' => [['id', 'uuid', 'name', 'code', 'is_active', 'is_primary']],
                    'permissions',
                ],
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
