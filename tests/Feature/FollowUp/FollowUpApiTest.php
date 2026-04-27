<?php

namespace Tests\Feature\FollowUp;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\FollowUp;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FollowUpApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function bootEnv(): array
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        $user->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($user);

        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $proc = Procedure::factory()->create(['branch_id' => $branch->id, 'follow_up_days' => 7]);
        $doctor = User::factory()->create(['branch_id' => $branch->id, 'is_doctor' => true]);

        return [$user, $branch, $patient, $proc, $doctor];
    }

    private function makeFollow(int $branchId, int $patientId, ?int $doctorId, ?int $procId, string $date, string $priority = 'normal', string $status = 'pending'): FollowUp
    {
        return FollowUp::create([
            'branch_id' => $branchId,
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'procedure_id' => $procId,
            'follow_up_date' => $date,
            'priority' => $priority,
            'status' => $status,
        ]);
    }

    public function test_index_lists_with_priority_ordering(): void
    {
        [, $branch, $patient, $proc, $doctor] = $this->bootEnv();

        $this->makeFollow($branch->id, $patient->id, $doctor->id, $proc->id, now()->addDays(5)->toDateString(), 'low');
        $this->makeFollow($branch->id, $patient->id, $doctor->id, $proc->id, now()->subDays(8)->toDateString(), 'critical');
        $this->makeFollow($branch->id, $patient->id, $doctor->id, $proc->id, now()->addDay()->toDateString(), 'normal');

        $res = $this->getJson('/api/v1/follow-ups', ['X-Branch-Id' => $branch->id])->assertOk();
        $first = $res->json('data.0');
        $this->assertSame('critical', $first['priority']);
    }

    public function test_status_invalid_transition_returns_422(): void
    {
        [, $branch, $patient, $proc, $doctor] = $this->bootEnv();
        $f = $this->makeFollow($branch->id, $patient->id, $doctor->id, $proc->id, now()->toDateString(), 'normal', 'completed');

        $this->patchJson("/api/v1/follow-ups/{$f->id}/status", ['status' => 'pending'], ['X-Branch-Id' => $branch->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_transition');
    }

    public function test_record_contact_increments_attempts_and_optionally_changes_status(): void
    {
        [, $branch, $patient, $proc, $doctor] = $this->bootEnv();
        $f = $this->makeFollow($branch->id, $patient->id, $doctor->id, $proc->id, now()->toDateString(), 'normal', 'pending');

        $res = $this->postJson("/api/v1/follow-ups/{$f->id}/contact", [
            'notes' => 'โทรไม่รับ',
            'mark_status' => 'contacted',
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $this->assertSame(1, $res->json('data.contact_attempts'));
        $this->assertSame('contacted', $res->json('data.status'));
    }

    public function test_quick_book_creates_appointment_and_updates_follow_up(): void
    {
        [, $branch, $patient, $proc, $doctor] = $this->bootEnv();
        $f = $this->makeFollow($branch->id, $patient->id, $doctor->id, $proc->id, now()->toDateString());

        $tomorrow = now()->addDay()->toDateString();

        $res = $this->postJson('/api/v1/appointments/quick-create', [
            'follow_up_id' => $f->id,
            'appointment_date' => $tomorrow,
            'start_time' => '10:00',
            'end_time' => '10:30',
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $this->assertSame('scheduled', $res->json('data.follow_up.status'));
        $this->assertNotEmpty($res->json('data.appointment.id'));

        // appointment should reference follow_up_id
        $this->assertDatabaseHas('appointments', [
            'follow_up_id' => $f->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);
    }

    public function test_quick_book_conflict_returns_409(): void
    {
        [, $branch, $patient, $proc, $doctor] = $this->bootEnv();
        $f = $this->makeFollow($branch->id, $patient->id, $doctor->id, $proc->id, now()->toDateString());
        $tomorrow = now()->addDay()->toDateString();

        // Pre-existing appointment overlapping
        Appointment::create([
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => $tomorrow,
            'start_time' => '10:00:00',
            'end_time' => '10:45:00',
            'status' => 'pending',
            'source' => 'manual',
            'created_by' => $doctor->id,
        ]);

        $this->postJson('/api/v1/appointments/quick-create', [
            'follow_up_id' => $f->id,
            'appointment_date' => $tomorrow,
            'start_time' => '10:30',
            'end_time' => '11:00',
        ], ['X-Branch-Id' => $branch->id])
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict');
    }

    public function test_stats_returns_counts(): void
    {
        [, $branch, $patient, $proc, $doctor] = $this->bootEnv();
        $this->makeFollow($branch->id, $patient->id, $doctor->id, $proc->id, now()->subDays(8)->toDateString(), 'critical');
        $this->makeFollow($branch->id, $patient->id, $doctor->id, $proc->id, now()->subDays(4)->toDateString(), 'high');
        $this->makeFollow($branch->id, $patient->id, $doctor->id, $proc->id, now()->toDateString(), 'normal');

        $res = $this->getJson('/api/v1/follow-ups/stats', ['X-Branch-Id' => $branch->id])->assertOk();
        $this->assertSame(1, $res->json('data.critical'));
        $this->assertSame(1, $res->json('data.high'));
        $this->assertSame(1, $res->json('data.normal'));
        $this->assertSame(3, $res->json('data.total'));
    }
}
