<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberCourseFlowTest extends TestCase
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

    public function test_deposit_via_api_creates_account_and_returns_balance(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $res = $this->postJson("/api/v1/members/{$patient->uuid}/deposit", [
            'amount' => 5000,
            'package_name' => 'Promo 5K',
            'expires_at' => now()->addYear()->toDateString(),
            'notes' => 'first',
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->assertSame(5000.0, (float) $res->json('data.account.balance'));
        $this->assertDatabaseHas('member_accounts', [
            'patient_id' => $patient->id, 'balance' => 5000, 'lifetime_topups' => 1,
        ]);
    }

    public function test_checkout_creates_course_for_package_procedure(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $pkg = Procedure::factory()->create([
            'branch_id' => $branch->id, 'price' => 12000, 'cost' => 0,
            'doctor_fee_rate' => 0, 'staff_commission_rate' => 0, 'follow_up_days' => 0,
            'is_package' => true, 'package_sessions' => 4, 'package_validity_days' => 180,
        ]);

        $vUuid = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
            ->json('data.id');
        $this->postJson("/api/v1/visits/$vUuid/invoice-items", [
            'item_type' => 'procedure', 'item_id' => $pkg->id, 'quantity' => 1,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);
        $this->postJson("/api/v1/visits/$vUuid/checkout", [
            'payments' => [['method' => 'cash', 'amount' => 12000]],
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $course = Course::query()->where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame(4, (int) $course->total_sessions);
        $this->assertSame(0, (int) $course->used_sessions);
        $this->assertSame('active', $course->status);
        $this->assertNotNull($course->expires_at);
    }

    public function test_use_session_via_api_decrements_remaining(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $course = Course::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'name' => 'Pkg', 'total_sessions' => 3, 'used_sessions' => 0, 'remaining_sessions' => 3,
            'status' => 'active',
        ]);
        $vUuid = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
            ->json('data.id');

        $res = $this->postJson("/api/v1/courses/{$course->id}/use-session", [
            'visit_uuid' => $vUuid,
            'notes' => 'session 1',
        ], ['X-Branch-Id' => $branch->id])->assertCreated();

        $this->assertSame(1, (int) $res->json('data.usage.session_number'));
        $this->assertSame(2, (int) $res->json('data.course.remaining_sessions'));
    }

    public function test_checkout_with_course_item_consumes_session_alongside_fee(): void
    {
        [$branch] = $this->bootAdmin();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $course = Course::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'name' => 'Pkg', 'total_sessions' => 2, 'used_sessions' => 0, 'remaining_sessions' => 2,
            'status' => 'active',
        ]);
        $consultFee = Procedure::factory()->create([
            'branch_id' => $branch->id, 'price' => 200, 'cost' => 0,
            'doctor_fee_rate' => 0, 'staff_commission_rate' => 0, 'follow_up_days' => 0,
            'is_package' => false,
        ]);

        $vUuid = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
            ->json('data.id');
        $this->postJson("/api/v1/visits/$vUuid/invoice-items", [
            'item_type' => 'course', 'item_id' => $course->id, 'quantity' => 1,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);
        $this->postJson("/api/v1/visits/$vUuid/invoice-items", [
            'item_type' => 'procedure', 'item_id' => $consultFee->id, 'quantity' => 1,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);
        $this->postJson("/api/v1/visits/$vUuid/checkout", [
            'payments' => [['method' => 'cash', 'amount' => 200]],
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        $course->refresh();
        $this->assertSame(1, (int) $course->used_sessions);
        $this->assertSame(1, (int) $course->remaining_sessions);
    }
}
