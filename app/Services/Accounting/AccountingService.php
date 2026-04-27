<?php

namespace App\Services\Accounting;

use App\Models\AccountingEntry;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    /**
     * Post a balanced journal entry (debit==credit). Each line is a single row
     * with debit_account + credit_account + amount.
     *
     * @param array<int, array{debit_account_id?:int, debit_code?:string, credit_account_id?:int, credit_code?:string, amount:float|int, description?:string}> $lines
     */
    public function post(int $branchId, string $documentType, ?int $documentId, array $lines, ?string $journalNo = null, ?string $entryDate = null, ?User $user = null): array
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => 'lines required']);
        }
        $entryDate = $entryDate ?? now()->toDateString();
        $journalNo = $journalNo ?: $this->nextJournalNo($branchId, $entryDate);

        $coaByCode = ChartOfAccount::query()
            ->where('branch_id', $branchId)
            ->get(['id', 'code'])
            ->keyBy('code');

        return DB::transaction(function () use ($branchId, $documentType, $documentId, $lines, $journalNo, $entryDate, $user, $coaByCode) {
            $created = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($lines as $line) {
                $debitId = $line['debit_account_id'] ?? null;
                $creditId = $line['credit_account_id'] ?? null;
                if (! $debitId && ! empty($line['debit_code'])) {
                    $debitId = $coaByCode->get($line['debit_code'])?->id;
                }
                if (! $creditId && ! empty($line['credit_code'])) {
                    $creditId = $coaByCode->get($line['credit_code'])?->id;
                }
                if (! $debitId || ! $creditId) {
                    throw ValidationException::withMessages(['lines' => 'missing CoA for line: '.json_encode($line, JSON_UNESCAPED_UNICODE)]);
                }
                $amount = round((float) $line['amount'], 2);
                if ($amount <= 0) {
                    continue;
                }

                $entry = AccountingEntry::create([
                    'branch_id' => $branchId,
                    'entry_date' => $entryDate,
                    'journal_no' => $journalNo,
                    'document_type' => $documentType,
                    'document_id' => $documentId,
                    'debit_account_id' => $debitId,
                    'credit_account_id' => $creditId,
                    'amount' => $amount,
                    'description' => $line['description'] ?? null,
                    'posted_by' => $user?->id,
                ]);
                $created[] = $entry;
                $totalDebit += $amount;
                $totalCredit += $amount;
            }

            // Each row is single-line balanced (debit == credit by construction in our schema)
            return $created;
        });
    }

    /**
     * Reverse all entries for a document by posting equal counter-entries.
     */
    public function reverse(int $branchId, string $documentType, int $documentId, ?User $user = null, ?string $reason = null): int
    {
        $entries = AccountingEntry::query()
            ->where('branch_id', $branchId)
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->get();

        if ($entries->isEmpty()) {
            return 0;
        }

        $journal = 'REV-'.now()->format('YmdHis');
        DB::transaction(function () use ($entries, $journal, $user, $reason) {
            foreach ($entries as $e) {
                AccountingEntry::create([
                    'branch_id' => $e->branch_id,
                    'entry_date' => now()->toDateString(),
                    'journal_no' => $journal,
                    'document_type' => $e->document_type.':reverse',
                    'document_id' => $e->document_id,
                    'debit_account_id' => $e->credit_account_id,
                    'credit_account_id' => $e->debit_account_id,
                    'amount' => $e->amount,
                    'description' => '[REVERSE] '.($reason ?? '').' (orig journal '.$e->journal_no.')',
                    'posted_by' => $user?->id,
                ]);
            }
        });

        return $entries->count();
    }

    public function nextJournalNo(int $branchId, string $entryDate): string
    {
        $prefix = 'J'.Carbon::parse($entryDate)->format('Ymd').'-';

        return DB::transaction(function () use ($branchId, $prefix) {
            $last = AccountingEntry::query()
                ->where('branch_id', $branchId)
                ->where('journal_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('journal_no')
                ->value('journal_no');
            $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

            return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }

    public function getCoaByCode(int $branchId, string $code): ?ChartOfAccount
    {
        return ChartOfAccount::query()
            ->where('branch_id', $branchId)
            ->where('code', $code)
            ->first();
    }
}
