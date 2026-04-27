<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\MemberAccount;
use App\Models\Patient;
use App\Models\User;
use App\Services\MemberWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MemberWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bootEnv(): array
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $user = User::factory()->create(['branch_id' => $branch->id]);

        return [$branch, $patient, $user];
    }

    public function test_deposit_creates_account_and_increments_balance(): void
    {
        [, $patient, $user] = $this->bootEnv();
        $svc = $this->app->make(MemberWalletService::class);

        $svc->deposit($patient, 5000, $user, 'Promo 5000', notes: 'first topup');
        $svc->deposit($patient, 2000, $user);

        $acc = MemberAccount::query()->where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame('7000.00', (string) $acc->balance);
        $this->assertSame('7000.00', (string) $acc->total_deposit);
        $this->assertSame(2, (int) $acc->lifetime_topups);
        $this->assertNotNull($acc->last_topup_at);
        $this->assertDatabaseCount('member_transactions', 2);
    }

    public function test_deposit_rejects_non_positive(): void
    {
        [, $patient] = $this->bootEnv();
        $this->expectException(ValidationException::class);
        $this->app->make(MemberWalletService::class)->deposit($patient, 0);
    }

    public function test_refund_increases_balance_and_decreases_total_used(): void
    {
        [$branch, $patient] = $this->bootEnv();
        $acc = MemberAccount::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'balance' => 1000, 'total_deposit' => 5000, 'total_used' => 4000, 'status' => 'active',
        ]);

        $this->app->make(MemberWalletService::class)->refund($acc, 200);
        $acc->refresh();

        $this->assertSame('1200.00', (string) $acc->balance);
        $this->assertSame('3800.00', (string) $acc->total_used);
    }

    public function test_adjust_handles_positive_and_negative_with_floor_zero(): void
    {
        [$branch, $patient] = $this->bootEnv();
        $acc = MemberAccount::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'balance' => 100, 'total_deposit' => 100, 'total_used' => 0, 'status' => 'active',
        ]);
        $svc = $this->app->make(MemberWalletService::class);

        $svc->adjust($acc, 50, 'bonus');
        $acc->refresh();
        $this->assertSame('150.00', (string) $acc->balance);

        $svc->adjust($acc, -50, 'fix');
        $acc->refresh();
        $this->assertSame('100.00', (string) $acc->balance);

        $this->expectException(ValidationException::class);
        $svc->adjust($acc, -200, 'overdraw');
    }
}
