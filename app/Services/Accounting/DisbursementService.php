<?php

namespace App\Services\Accounting;

use App\Models\Disbursement;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DisbursementService
{
    public function __construct(private AccountingPoster $poster) {}

    public function nextNumber(int $branchId): string
    {
        $prefix = 'DSB'.now()->format('Ymd').'-';
        $last = Disbursement::query()
            ->where('branch_id', $branchId)
            ->where('disbursement_no', 'like', $prefix.'%')
            ->orderByDesc('disbursement_no')
            ->value('disbursement_no');
        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function approve(Disbursement $disbursement, User $approver): Disbursement
    {
        if ($disbursement->status !== 'draft') {
            throw ValidationException::withMessages(['status' => "must be draft (current: {$disbursement->status})"]);
        }
        $disbursement->status = 'approved';
        $disbursement->approved_by = $approver->id;
        $disbursement->approved_at = now();
        $disbursement->save();

        return $disbursement->fresh();
    }

    public function pay(Disbursement $disbursement, ?string $reference = null, ?User $user = null): Disbursement
    {
        if ($disbursement->status !== 'approved') {
            throw ValidationException::withMessages(['status' => "must be approved (current: {$disbursement->status})"]);
        }

        $disbursement->status = 'paid';
        $disbursement->paid_at = now();
        if ($reference) {
            $disbursement->reference = $reference;
        }
        $disbursement->save();

        // Post accounting
        try {
            $this->poster->postDisbursement($disbursement->fresh());
        } catch (\Throwable $e) {
            Log::warning('Accounting post failed for disbursement '.$disbursement->id.': '.$e->getMessage());
        }

        return $disbursement->fresh();
    }

    public function cancel(Disbursement $disbursement, string $reason, ?User $user = null): Disbursement
    {
        if ($disbursement->status === 'paid') {
            throw ValidationException::withMessages(['status' => 'paid disbursement cannot be cancelled (issue reverse instead)']);
        }
        $disbursement->status = 'cancelled';
        $disbursement->description = trim(($disbursement->description ? $disbursement->description."\n" : '').'[CANCEL] '.$reason);
        $disbursement->save();

        return $disbursement->fresh();
    }
}
