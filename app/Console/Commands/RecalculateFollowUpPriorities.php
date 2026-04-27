<?php

namespace App\Console\Commands;

use App\Services\FollowUpPriorityService;
use Illuminate\Console\Command;

class RecalculateFollowUpPriorities extends Command
{
    protected $signature = 'follow-ups:recalc-priorities {--branch= : Limit to a specific branch_id}';

    protected $description = 'Recalculate follow-up priorities (critical/high/normal/low) based on overdue rules';

    public function handle(FollowUpPriorityService $svc): int
    {
        $branchId = $this->option('branch');
        $branchId = $branchId !== null ? (int) $branchId : null;

        $count = $svc->recalculateAll($branchId);
        $this->info("Updated {$count} follow-ups");

        return self::SUCCESS;
    }
}
