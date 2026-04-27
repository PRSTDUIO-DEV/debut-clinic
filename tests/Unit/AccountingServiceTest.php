<?php

namespace Tests\Unit;

use App\Models\AccountingEntry;
use App\Models\Branch;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bootBranch(): Branch
    {
        $branch = Branch::factory()->create();
        $this->app->make(ChartOfAccountSeeder::class)->seed($branch->id);

        return $branch;
    }

    public function test_post_with_codes_creates_entries(): void
    {
        $branch = $this->bootBranch();
        $svc = $this->app->make(AccountingService::class);

        $entries = $svc->post(
            branchId: $branch->id,
            documentType: 'test',
            documentId: 1,
            lines: [[
                'debit_code' => '1100',
                'credit_code' => '4100',
                'amount' => 1000,
                'description' => 'cash sale',
            ]],
        );

        $this->assertCount(1, $entries);
        $this->assertSame('1000.00', (string) $entries[0]->amount);
        $this->assertNotEmpty($entries[0]->journal_no);
    }

    public function test_post_throws_when_code_unknown(): void
    {
        $branch = $this->bootBranch();
        $svc = $this->app->make(AccountingService::class);

        $this->expectException(ValidationException::class);
        $svc->post($branch->id, 'test', 1, [['debit_code' => '9999', 'credit_code' => '4100', 'amount' => 100]]);
    }

    public function test_journal_numbers_increment_per_branch_per_day(): void
    {
        $branch = $this->bootBranch();
        $svc = $this->app->make(AccountingService::class);
        $a = $svc->post($branch->id, 'test', 1, [['debit_code' => '1100', 'credit_code' => '4100', 'amount' => 50]]);
        $b = $svc->post($branch->id, 'test', 2, [['debit_code' => '1100', 'credit_code' => '4100', 'amount' => 60]]);

        $this->assertNotSame($a[0]->journal_no, $b[0]->journal_no);
        $aSeq = (int) substr($a[0]->journal_no, -4);
        $bSeq = (int) substr($b[0]->journal_no, -4);
        $this->assertSame($aSeq + 1, $bSeq);
    }

    public function test_reverse_creates_counter_entries(): void
    {
        $branch = $this->bootBranch();
        $svc = $this->app->make(AccountingService::class);
        $svc->post($branch->id, 'test', 5, [['debit_code' => '1100', 'credit_code' => '4100', 'amount' => 200]]);

        $count = $svc->reverse($branch->id, 'test', 5, null, 'oops');
        $this->assertSame(1, $count);

        $reversed = AccountingEntry::query()->where('document_type', 'test:reverse')->get();
        $this->assertCount(1, $reversed);
        // Debit/credit swapped
        $this->assertSame('200.00', (string) $reversed[0]->amount);
    }
}
