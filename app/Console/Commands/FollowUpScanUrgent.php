<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Services\UrgentFollowUpScanner;
use Illuminate\Console\Command;

class FollowUpScanUrgent extends Command
{
    protected $signature = 'follow-up:scan-urgent {--branch= : Optional branch_id}';

    protected $description = 'Scan follow_up_rules and write notifications for urgent patient situations.';

    public function handle(UrgentFollowUpScanner $scanner, NotificationService $notifications): int
    {
        $branch = $this->option('branch') ? (int) $this->option('branch') : null;
        $written = $scanner->run($branch);
        $this->info("Wrote {$written} urgent follow-up notifications");

        $sent = $notifications->dispatchPending(500);
        $this->info("Dispatched {$sent} pending notifications");

        return self::SUCCESS;
    }
}
