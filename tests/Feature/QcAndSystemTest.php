<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CommandRun;
use App\Models\QcChecklist;
use App\Models\QcChecklistItem;
use App\Models\QcRun;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\SystemHealthService;
use App\Services\Qc\QcService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QcAndSystemTest extends TestCase
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

    public function test_qc_create_with_items_and_start_run(): void
    {
        [$branch] = $this->bootAdmin();

        $r = $this->postJson('/api/v1/qc/checklists', [
            'name' => 'Daily Cleaning',
            'frequency' => 'daily',
            'items' => [
                ['title' => 'ทำความสะอาดห้องตรวจ'],
                ['title' => 'ตรวจสอบยาหมดอายุ', 'requires_note' => true],
                ['title' => 'ตรวจสอบเครื่องมือพร้อมใช้'],
            ],
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->assertSame(3, count($r->json('data.items')));
        $clId = $r->json('data.id');

        $run = $this->postJson('/api/v1/qc/runs', ['checklist_id' => $clId], ['X-Branch-Id' => $branch->id])
            ->assertCreated();

        $this->assertSame('in_progress', $run->json('data.status'));
        $this->assertSame(3, $run->json('data.total_items'));
    }

    public function test_qc_record_items_and_complete_rolls_up_counts(): void
    {
        [$branch, $admin] = $this->bootAdmin();
        $cl = QcChecklist::create(['branch_id' => $branch->id, 'name' => 'X', 'frequency' => 'daily']);
        $i1 = QcChecklistItem::create(['checklist_id' => $cl->id, 'position' => 0, 'title' => 'A']);
        $i2 = QcChecklistItem::create(['checklist_id' => $cl->id, 'position' => 1, 'title' => 'B']);
        $i3 = QcChecklistItem::create(['checklist_id' => $cl->id, 'position' => 2, 'title' => 'C']);

        $svc = app(QcService::class);
        $run = $svc->startRun($cl, $admin);
        $svc->recordItem($run, $i1, 'pass');
        $svc->recordItem($run, $i2, 'fail', 'พบปัญหา');
        $svc->recordItem($run, $i3, 'na');
        $svc->completeRun($run);

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame(3, $run->total_items);
        $this->assertSame(1, $run->passed_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertSame(1, $run->na_count);
    }

    public function test_qc_record_after_complete_throws(): void
    {
        [$branch, $admin] = $this->bootAdmin();
        $cl = QcChecklist::create(['branch_id' => $branch->id, 'name' => 'X', 'frequency' => 'daily']);
        $item = QcChecklistItem::create(['checklist_id' => $cl->id, 'position' => 0, 'title' => 'A']);

        $svc = app(QcService::class);
        $run = $svc->startRun($cl, $admin);
        $svc->recordItem($run, $item, 'pass');
        $svc->completeRun($run);

        $this->expectException(ValidationException::class);
        $svc->recordItem($run->fresh(), $item, 'fail');
    }

    public function test_qc_summary_aggregates_correctly(): void
    {
        [$branch] = $this->bootAdmin();
        $cl = QcChecklist::create(['branch_id' => $branch->id, 'name' => 'X', 'frequency' => 'daily']);
        QcRun::create([
            'checklist_id' => $cl->id, 'branch_id' => $branch->id,
            'run_date' => now()->subDay(), 'status' => 'completed',
            'total_items' => 5, 'passed_count' => 4, 'failed_count' => 1, 'na_count' => 0,
        ]);
        QcRun::create([
            'checklist_id' => $cl->id, 'branch_id' => $branch->id,
            'run_date' => now(), 'status' => 'completed',
            'total_items' => 5, 'passed_count' => 5, 'failed_count' => 0, 'na_count' => 0,
        ]);

        $r = $this->getJson('/api/v1/qc/summary', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertSame(2, $r->json('data.runs'));
        $this->assertSame(10, $r->json('data.total_items'));
        $this->assertSame(9, $r->json('data.passed'));
        $this->assertSame(90.0, (float) $r->json('data.pass_rate_pct'));
    }

    public function test_audit_log_show_returns_diff(): void
    {
        [$branch] = $this->bootAdmin();
        $log = AuditLog::create([
            'branch_id' => $branch->id, 'user_id' => null, 'action' => 'updated',
            'auditable_type' => 'App\\Models\\Patient', 'auditable_id' => 1,
            'old_values' => ['phone' => '0811111111', 'name' => 'A'],
            'new_values' => ['phone' => '0822222222', 'name' => 'A'],
            'created_at' => now(),
        ]);

        $r = $this->getJson("/api/v1/audit-logs/{$log->id}", ['X-Branch-Id' => $branch->id])
            ->assertOk();

        $this->assertSame(['phone'], $r->json('data.changed_fields'));
        $this->assertSame('0811111111', $r->json('data.diff.phone.before'));
        $this->assertSame('0822222222', $r->json('data.diff.phone.after'));
    }

    public function test_audit_export_streams_csv(): void
    {
        [$branch] = $this->bootAdmin();
        AuditLog::create([
            'branch_id' => $branch->id, 'user_id' => null, 'action' => 'created',
            'auditable_type' => 'X', 'auditable_id' => 1, 'created_at' => now(),
        ]);

        $res = $this->call('GET', '/api/v1/audit-logs/export', [], [], [], ['HTTP_X-Branch-Id' => $branch->id]);
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
    }

    public function test_system_health_snapshot_returns_status(): void
    {
        [$branch] = $this->bootAdmin();

        $r = $this->getJson('/api/v1/admin/system-health', ['X-Branch-Id' => $branch->id])
            ->assertOk();
        $this->assertArrayHasKey('app', $r->json('data'));
        $this->assertArrayHasKey('database', $r->json('data'));
        $this->assertArrayHasKey('cron', $r->json('data'));
        $this->assertArrayHasKey('storage', $r->json('data'));
    }

    public function test_command_runs_logged_via_service(): void
    {
        $svc = app(SystemHealthService::class);
        $r = $svc->logCommand('test:hello', fn () => 'ok output');
        $this->assertSame(0, $r['exit']);
        $this->assertSame(1, CommandRun::where('command', 'test:hello')->count());

        // Failing command
        $r2 = $svc->logCommand('test:fail', fn () => throw new \RuntimeException('boom'));
        $this->assertSame(1, $r2['exit']);
        $this->assertSame(1, CommandRun::where('command', 'test:fail')->where('exit_code', 1)->count());
    }

    public function test_qc_view_permission_required(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        $user->roles()->attach(Role::where('name', 'receptionist')->first()->id);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/qc/checklists', ['X-Branch-Id' => $branch->id])->assertStatus(403);
    }

    public function test_nurse_can_perform_qc_but_not_manage(): void
    {
        $branch = Branch::factory()->create();
        $nurse = User::factory()->create(['branch_id' => $branch->id]);
        $nurse->branches()->attach($branch->id, ['is_primary' => true]);
        $nurse->roles()->attach(Role::where('name', 'nurse')->first()->id);
        Sanctum::actingAs($nurse);

        $this->getJson('/api/v1/qc/checklists', ['X-Branch-Id' => $branch->id])->assertOk();

        // Create checklist as super-admin first
        $cl = QcChecklist::create(['branch_id' => $branch->id, 'name' => 'X', 'frequency' => 'daily']);
        $this->postJson('/api/v1/qc/runs', ['checklist_id' => $cl->id], ['X-Branch-Id' => $branch->id])
            ->assertCreated();

        // But cannot manage (create new checklist)
        $this->postJson('/api/v1/qc/checklists', ['name' => 'Y', 'frequency' => 'daily'], ['X-Branch-Id' => $branch->id])
            ->assertStatus(403);
    }
}
