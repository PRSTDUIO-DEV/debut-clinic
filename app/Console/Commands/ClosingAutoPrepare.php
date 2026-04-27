<?php

namespace App\Console\Commands;

use App\Services\ClosingService;
use Illuminate\Console\Command;

class ClosingAutoPrepare extends Command
{
    protected $signature = 'closing:auto-prepare {--branch= : Optional branch_id}';

    protected $description = 'Prepare a draft daily closing for yesterday across all branches.';

    public function handle(ClosingService $service): int
    {
        $branch = $this->option('branch') ? (int) $this->option('branch') : null;
        $count = $service->autoPrepareYesterday($branch);
        $this->info("Prepared {$count} branch(es) for ".now()->subDay()->toDateString());

        return self::SUCCESS;
    }
}
