<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_checkout_writes_commission_transactions_using_procedure_default(): void
    {
        $branch = Branch::factory()->create();
        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->branches()->attach($branch->id, ['is_primary' => true]);
        $admin->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($admin);

        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $doctor = User::factory()->create(['branch_id' => $branch->id, 'is_doctor' => true]);
        $proc = Procedure::factory()->create([
            'branch_id' => $branch->id,
            'price' => 1000,
            'cost' => 200,
            'doctor_fee_rate' => 30,
            'staff_commission_rate' => 5,
            'follow_up_days' => 0,
        ]);

        $vUuid = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
            ->json('data.id');

        $this->postJson("/api/v1/visits/$vUuid/invoice-items", [
            'item_type' => 'procedure', 'item_id' => $proc->id, 'quantity' => 1,
            'doctor_id' => $doctor->id,
        ], ['X-Branch-Id' => $branch->id])->assertStatus(201);

        $this->postJson("/api/v1/visits/$vUuid/checkout", [
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ], ['X-Branch-Id' => $branch->id])->assertOk();

        // Doctor should have 30% of 1000 = 300
        $this->assertDatabaseHas('commission_transactions', [
            'user_id' => $doctor->id,
            'type' => 'doctor_fee',
            'amount' => 300.00,
        ]);

        // Invoice.total_commission should reflect aggregate
        $this->assertDatabaseHas('invoices', [
            'visit_id' => Visit::query()->where('uuid', $vUuid)->value('id'),
            'total_commission' => 300.00,
        ]);
    }

    public function test_payment_mix_endpoint_aggregates_paid_invoices(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->branches()->attach($branch->id, ['is_primary' => true]);
        $user->roles()->attach(Role::where('name', 'super_admin')->first()->id);
        Sanctum::actingAs($user);

        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $proc = Procedure::factory()->create([
            'branch_id' => $branch->id, 'price' => 500, 'cost' => 0,
            'doctor_fee_rate' => 0, 'staff_commission_rate' => 0, 'follow_up_days' => 0,
        ]);

        // Create + checkout 2 visits with different payment methods
        foreach (['cash', 'transfer'] as $method) {
            $v = $this->postJson('/api/v1/visits', ['patient_uuid' => $patient->uuid], ['X-Branch-Id' => $branch->id])
                ->json('data.id');
            $this->postJson("/api/v1/visits/$v/invoice-items", [
                'item_type' => 'procedure', 'item_id' => $proc->id, 'quantity' => 1,
            ], ['X-Branch-Id' => $branch->id]);
            $this->postJson("/api/v1/visits/$v/checkout", [
                'payments' => [['method' => $method, 'amount' => 500]],
            ], ['X-Branch-Id' => $branch->id])->assertOk();
        }

        $today = now()->toDateString();
        $res = $this->getJson("/api/v1/reports/payment-mix?date_from=$today&date_to=$today", ['X-Branch-Id' => $branch->id])
            ->assertOk();

        $this->assertEquals(1000, $res->json('data.grand_total'));
        $methods = collect($res->json('data.by_method'))->keyBy('method');
        $this->assertSame(500.0, (float) $methods->get('cash')['total']);
        $this->assertSame(500.0, (float) $methods->get('transfer')['total']);
    }
}
