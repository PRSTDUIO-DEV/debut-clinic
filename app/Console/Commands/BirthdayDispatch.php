<?php

namespace App\Console\Commands;

use App\Services\BirthdayCampaignService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class BirthdayDispatch extends Command
{
    protected $signature = 'birthday:dispatch {--date= : Specific date to run for (YYYY-MM-DD)} {--branch= : Optional branch_id}';

    protected $description = 'Run all active birthday campaigns for today (or specified date) and dispatch notifications.';

    public function handle(BirthdayCampaignService $svc, NotificationService $notify): int
    {
        $branch = $this->option('branch') ? (int) $this->option('branch') : null;
        $written = $svc->runAll($this->option('date'), $branch);
        $this->info("Wrote {$written} birthday notifications");

        $sent = $notify->dispatchPending(500);
        $this->info("Dispatched {$sent} pending notifications");

        return self::SUCCESS;
    }
}
