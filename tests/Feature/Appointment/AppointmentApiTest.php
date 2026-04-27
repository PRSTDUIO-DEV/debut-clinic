<?php

namespace Tests\Feature\Appointment;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentApiTest extends TestCase
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

        $doctor = User::factory()->create(['branch_id' => $branch->id, 'is_doctor' => true]);
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $room = Room::factory()->create(['branch_id' => $branch->id]);

        return [$user, $branch, $doctor, $patient, $room];
    }

    public function test_create_appointment_success(): void
    {
        [, $branch, $doctor, $patient, $room] = $this->bootUser();

        $tomorrow = now()->addDay()->toDateString();

        $this->postJson('/api/v1/appointments', [
            'patient_uuid' => $patient->uuid,
            'doctor_id' => $doctor->id,
            'room_id' => $room->id,
            'appointment_date' => $tomorrow,
            'start_time' => '10:00',
            'end_time' => '10:30',
        ], ['X-Branch-Id' => $branch->id])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_overlapping_appointment_returns_409(): void
    {
        [, $branch, $doctor, $patient, $room] = $this->bootUser();
        $tomorrow = now()->addDay()->toDateString();

        $this->postJson('/api/v1/appointments', [
            'patient_uuid' => $patient->uuid,
            'doctor_id' => $doctor->id,
            'room_id' => $room->id,
            'appointment_date' => $tomorrow,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $this->postJson('/api/v1/appointments', [
            'patient_uuid' => $patient->uuid,
            'doctor_id' => $doctor->id,
            'room_id' => $room->id,
            'appointment_date' => $tomorrow,
            'start_time' => '10:30',
            'end_time' => '11:30',
        ], ['X-Branch-Id' => $branch->id])
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict');
    }

    public function test_status_transition_pending_to_confirmed(): void
    {
        [, $branch, $doctor, $patient, $room] = $this->bootUser();
        $tomorrow = now()->addDay()->toDateString();

        $apptId = $this->postJson('/api/v1/appointments', [
            'patient_uuid' => $patient->uuid,
            'doctor_id' => $doctor->id,
            'room_id' => $room->id,
            'appointment_date' => $tomorrow,
            'start_time' => '14:00',
            'end_time' => '14:30',
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201)->json('data.id');

        $this->patchJson('/api/v1/appointments/'.$apptId.'/status', ['status' => 'confirmed'], ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_invalid_status_transition_returns_422(): void
    {
        [, $branch, $doctor, $patient, $room] = $this->bootUser();
        $tomorrow = now()->addDay()->toDateString();

        $apptId = $this->postJson('/api/v1/appointments', [
            'patient_uuid' => $patient->uuid,
            'doctor_id' => $doctor->id,
            'appointment_date' => $tomorrow,
            'start_time' => '15:00',
            'end_time' => '15:30',
        ], ['X-Branch-Id' => $branch->id])->json('data.id');

        // pending → cancelled (allowed)
        $this->patchJson('/api/v1/appointments/'.$apptId.'/status', ['status' => 'cancelled'], ['X-Branch-Id' => $branch->id])
            ->assertOk();

        // cancelled → completed (not allowed)
        $this->patchJson('/api/v1/appointments/'.$apptId.'/status', ['status' => 'completed'], ['X-Branch-Id' => $branch->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_transition');
    }

    public function test_available_slots_excludes_booked_times(): void
    {
        [, $branch, $doctor, $patient] = $this->bootUser();
        $tomorrow = now()->addDay()->toDateString();

        $this->postJson('/api/v1/appointments', [
            'patient_uuid' => $patient->uuid,
            'doctor_id' => $doctor->id,
            'appointment_date' => $tomorrow,
            'start_time' => '10:00',
            'end_time' => '10:30',
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $res = $this->getJson("/api/v1/appointments/available-slots?doctor_id={$doctor->id}&date={$tomorrow}", ['X-Branch-Id' => $branch->id]);
        $res->assertOk();
        $slots = collect($res->json('data'));
        $this->assertFalse($slots->contains(fn ($s) => $s['start'] === '10:00'));
    }
}
