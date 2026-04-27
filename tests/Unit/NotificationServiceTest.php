<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\NotificationPreference;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_write_creates_pending_notification(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $svc = $this->app->make(NotificationService::class);
        $n = $svc->write('user', $user->id, 'test', 'Hello', 'World', 'info', 'in_app', null, null, $branch->id);

        $this->assertSame('pending', $n->status);
        $this->assertNull($n->read_at);
        $this->assertSame('Hello', $n->title);
    }

    public function test_dispatch_marks_sent_for_in_app(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $svc = $this->app->make(NotificationService::class);
        $n = $svc->write('user', $user->id, 'test', 'Hello', null);
        $svc->dispatch($n);
        $n->refresh();

        $this->assertSame('sent', $n->status);
        $this->assertNotNull($n->sent_at);
    }

    public function test_mark_read_sets_read_at(): void
    {
        $user = User::factory()->create();
        $svc = $this->app->make(NotificationService::class);
        $n = $svc->write('user', $user->id, 't', 'a', 'b');

        $svc->markRead($n);
        $n->refresh();

        $this->assertNotNull($n->read_at);
        $this->assertSame('read', $n->status);
    }

    public function test_mark_all_read_returns_count(): void
    {
        $user = User::factory()->create();
        $svc = $this->app->make(NotificationService::class);
        for ($i = 0; $i < 3; $i++) {
            $svc->write('user', $user->id, 't', "n{$i}", null);
        }

        $count = $svc->markAllRead($user->id);
        $this->assertSame(3, $count);
        $this->assertSame(0, $svc->unreadCount($user->id));
    }

    public function test_write_to_role_creates_one_per_user(): void
    {
        $branch = Branch::factory()->create();
        $admins = User::factory()->count(2)->create(['branch_id' => $branch->id]);
        $branchAdminRole = Role::query()->where('name', 'branch_admin')->firstOrFail();
        foreach ($admins as $a) {
            $a->roles()->attach($branchAdminRole->id);
        }

        $svc = $this->app->make(NotificationService::class);
        $rows = $svc->writeToRole(
            roleName: 'branch_admin', branchId: $branch->id,
            type: 'urgent_followup', title: 'Test', body: 'Body',
        );

        $this->assertCount(2, $rows);
    }

    public function test_preferences_disable_falls_back_to_in_app(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create(['user_id' => $user->id, 'channel' => 'line', 'enabled' => false]);

        $svc = $this->app->make(NotificationService::class);
        $n = $svc->write('user', $user->id, 't', 'X', null, 'info', 'line');
        $svc->dispatch($n);
        $n->refresh();

        // Resolved channel falls back to in_app
        $this->assertSame('in_app', $n->channel);
    }
}
