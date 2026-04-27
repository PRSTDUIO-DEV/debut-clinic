<?php

namespace App\Services;

use App\Models\MemberAccount;
use App\Models\MemberTransaction;
use App\Models\Patient;
use App\Models\User;
use App\Services\Accounting\AccountingPoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MemberWalletService
{
    /**
     * Return existing member account for patient or create one.
     */
    public function getOrCreate(Patient $patient, ?string $packageName = null, ?string $expiresAt = null): MemberAccount
    {
        return MemberAccount::firstOrCreate(
            ['patient_id' => $patient->id],
            [
                'branch_id' => $patient->branch_id,
                'package_name' => $packageName,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ],
        );
    }

    /**
     * Top up wallet (deposit).
     */
    public function deposit(
        Patient $patient,
        float $amount,
        ?User $user = null,
        ?string $packageName = null,
        ?string $expiresAt = null,
        ?string $notes = null,
    ): MemberTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'จำนวนเงินต้องมากกว่า 0']);
        }

        return DB::transaction(function () use ($patient, $amount, $user, $packageName, $expiresAt, $notes) {
            $account = MemberAccount::query()
                ->where('patient_id', $patient->id)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                $account = MemberAccount::create([
                    'branch_id' => $patient->branch_id,
                    'patient_id' => $patient->id,
                    'package_name' => $packageName,
                    'expires_at' => $expiresAt,
                    'status' => 'active',
                ]);
            } elseif ($packageName) {
                $account->package_name = $packageName;
            }
            if ($expiresAt) {
                $account->expires_at = $expiresAt;
            }

            $before = (float) $account->balance;
            $account->balance = $before + $amount;
            $account->total_deposit = (float) $account->total_deposit + $amount;
            $account->lifetime_topups = (int) $account->lifetime_topups + 1;
            $account->last_topup_at = now();
            if ($account->status === 'expired' && (! $account->expires_at || $account->expires_at->isFuture())) {
                $account->status = 'active';
            }
            $account->save();

            $txn = MemberTransaction::create([
                'member_account_id' => $account->id,
                'type' => 'deposit',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $account->balance,
                'notes' => $notes ?? 'Top up wallet',
                'created_by' => $user?->id,
            ]);
            $this->postAccounting($txn);

            return $txn;
        });
    }

    /**
     * Refund balance back to wallet (e.g. after a void invoice).
     */
    public function refund(MemberAccount $account, float $amount, ?int $invoiceId = null, ?User $user = null, ?string $notes = null): MemberTransaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'จำนวนเงินต้องมากกว่า 0']);
        }

        return DB::transaction(function () use ($account, $amount, $invoiceId, $user, $notes) {
            $account = MemberAccount::query()->lockForUpdate()->findOrFail($account->id);

            $before = (float) $account->balance;
            $account->balance = $before + $amount;
            $account->total_used = max(0, (float) $account->total_used - $amount);
            $account->save();

            $txn = MemberTransaction::create([
                'member_account_id' => $account->id,
                'type' => 'refund',
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $account->balance,
                'invoice_id' => $invoiceId,
                'notes' => $notes ?? 'Refund',
                'created_by' => $user?->id,
            ]);
            $this->postAccounting($txn);

            return $txn;
        });
    }

    /**
     * Manual adjustment (positive or negative).
     */
    public function adjust(MemberAccount $account, float $delta, string $reason, ?User $user = null): MemberTransaction
    {
        if ($delta == 0.0) {
            throw ValidationException::withMessages(['delta' => 'ค่าปรับต้องไม่เป็น 0']);
        }

        return DB::transaction(function () use ($account, $delta, $reason, $user) {
            $account = MemberAccount::query()->lockForUpdate()->findOrFail($account->id);

            $before = (float) $account->balance;
            $after = $before + $delta;
            if ($after < 0) {
                throw ValidationException::withMessages(['delta' => 'ยอดหลังปรับต้องไม่ติดลบ']);
            }
            $account->balance = $after;
            $account->save();

            $txn = MemberTransaction::create([
                'member_account_id' => $account->id,
                'type' => 'adjustment',
                'amount' => $delta,
                'balance_before' => $before,
                'balance_after' => $after,
                'notes' => $reason,
                'created_by' => $user?->id,
            ]);
            $this->postAccounting($txn);

            return $txn;
        });
    }

    private function postAccounting(MemberTransaction $txn): void
    {
        try {
            app(AccountingPoster::class)->postMemberTransaction($txn->fresh('memberAccount'));
        } catch (\Throwable $e) {
            Log::warning('Accounting post failed for member_transaction '.$txn->id.': '.$e->getMessage());
        }
    }
}
