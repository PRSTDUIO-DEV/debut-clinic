<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
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

    public function test_index_returns_only_self_notifications(): void
    {
        [$branch, $admin] = $this->bootAdmin();
        $other = User::factory()->create(['branch_id' => $branch->id]);

        $svc = $this->app->make(NotificationService::class);
        $svc->write('user', $admin->id, 't', 'mine', null, 'info', 'in_app', null, null, $branch->id);
        $svc->write('user', $other->id, 't', 'theirs', null, 'info', 'in_app', null, null, $branch->id);

        $res = $this->getJson('/api/v1/notifications', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('mine', $res->json('data.0.title'));
    }

    public function test_unread_count_endpoint(): void
    {
        [$branch, $admin] = $this->bootAdmin();
        $svc = $this->app->make(NotificationService::class);
        for ($i = 0; $i < 3; $i++) {
            $svc->write('user', $admin->id, 't', "n{$i}", null, 'info', 'in_app', null, null, $branch->id);
        }

        $res = $this->getJson('/api/v1/notifications/unread-count', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertSame(3, $res->json('data.count'));
    }

    public function test_mark_read_and_mark_all_read(): void
    {
        [$branch, $admin] = $this->bootAdmin();
        $svc = $this->app->make(NotificationService::class);
        $first = $svc->write('user', $admin->id, 't', 'a', null, 'info', 'in_app', null, null, $branch->id);
        $svc->write('user', $admin->id, 't', 'b', null, 'info', 'in_app', null, null, $branch->id);
        $svc->write('user', $admin->id, 't', 'c', null, 'info', 'in_app', null, null, $branch->id);

        $this->patchJson("/api/v1/notifications/{$first->id}/mark-read", [], ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertNotNull($first->fresh()->read_at);

        $this->postJson('/api/v1/notifications/mark-all-read', [], ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.marked', 2);
    }

    public function test_dashboard_summary_returns_widgets(): void
    {
        [$branch, $admin] = $this->bootAdmin();
        $svc = $this->app->make(NotificationService::class);
        $svc->write('user', $admin->id, 't', 'unread', null, 'info', 'in_app', null, null, $branch->id);

        $res = $this->getJson('/api/v1/dashboard/summary', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $data = $res->json('data');
        $this->assertArrayHasKey('unread_notifications', $data);
        $this->assertArrayHasKey('birthday_this_month', $data);
        $this->assertArrayHasKey('urgent_follow_ups', $data);
        $this->assertArrayHasKey('expired_stock', $data);
    }
}
