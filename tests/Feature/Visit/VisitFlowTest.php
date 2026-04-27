<?php

namespace Tests\Feature\Visit;

use App\Models\Branch;
use App\Models\MemberAccount;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitFlowTest extends TestCase
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
        $procedure = Procedure::factory()->create([
            'branch_id' => $branch->id,
            'price' => 1000,
            'cost' => 200,
            'follow_up_days' => 14,
        ]);
        $room = Room::factory()->create(['branch_id' => $branch->id]);

        return [$user, $branch, $patient, $procedure, $room];
    }

    public function test_open_visit_creates_draft_invoice(): void
    {
        [$user, $branch, $patient] = $this->bootEnv();

        $res = $this->postJson('/api/v1/visits', [
            'patient_uuid' => $patient->uuid,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $vUuid = $res->json('data.id');
        $this->assertNotEmpty($vUuid);
        $this->assertDatabaseHas('visits', ['uuid' => $vUuid, 'status' => 'in_progress']);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', ['status' => 'draft']);
    }

    public function test_full_checkout_creates_side_effects(): void
    {
        [$user, $branch, $patient, $procedure] = $this->bootEnv();

        $vUuid = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
            ->json('data.id');

        $this->postJson("/api/v1/visits/$vUuid/invoice-items", [
            'item_type' => 'procedure', 'item_id' => $procedure->id, 'quantity' => 1,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $this->postJson("/api/v1/visits/$vUuid/checkout", [
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ], ['X-Branch-Id' => $branch->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        // visit completed
        $this->assertDatabaseHas('visits', ['uuid' => $vUuid, 'status' => 'completed']);

        // payment recorded
        $this->assertDatabaseHas('payments', ['method' => 'cash', 'amount' => 1000]);

        // follow-up auto-created (procedure has follow_up_days=14)
        $this->assertDatabaseHas('follow_ups', ['patient_id' => $patient->id, 'priority' => 'normal']);

        // patient stats updated
        $patient->refresh();
        $this->assertEquals(1000, (float) $patient->total_spent);
        $this->assertSame(1, (int) $patient->visit_count);
    }

    public function test_payment_total_mismatch_rolls_back(): void
    {
        [$user, $branch, $patient, $procedure] = $this->bootEnv();

        $vUuid = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
            ->json('data.id');
        $this->postJson("/api/v1/visits/$vUuid/invoice-items", [
            'item_type' => 'procedure', 'item_id' => $procedure->id, 'quantity' => 1,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $this->postJson("/api/v1/visits/$vUuid/checkout", [
            'payments' => [['method' => 'cash', 'amount' => 500]], // wrong amount
        ], ['X-Branch-Id' => $branch->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payments']);

        // Visit still open, no follow_up
        $this->assertDatabaseHas('visits', ['uuid' => $vUuid, 'status' => 'in_progress']);
        $this->assertDatabaseMissing('follow_ups', ['patient_id' => $patient->id]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_member_credit_insufficient_balance_blocks_checkout(): void
    {
        [$user, $branch, $patient, $procedure] = $this->bootEnv();

        MemberAccount::create([
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'package_name' => 'Standard',
            'total_deposit' => 200,
            'total_used' => 0,
            'balance' => 200,
            'status' => 'active',
        ]);

        $vUuid = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
            ->json('data.id');
        $this->postJson("/api/v1/visits/$vUuid/invoice-items", [
            'item_type' => 'procedure', 'item_id' => $procedure->id, 'quantity' => 1,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $this->postJson("/api/v1/visits/$vUuid/checkout", [
            'payments' => [['method' => 'member_credit', 'amount' => 1000]],
        ], ['X-Branch-Id' => $branch->id])->assertStatus(422);

        // Member account untouched
        $this->assertDatabaseHas('member_accounts', ['patient_id' => $patient->id, 'balance' => 200]);
    }
}
