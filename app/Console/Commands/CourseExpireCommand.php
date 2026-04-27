<?php

namespace App\Console\Commands;

use App\Services\CourseService;
use Illuminate\Console\Command;

class CourseExpireCommand extends Command
{
    protected $signature = 'courses:expire {--branch= : Optional branch_id filter}';

    protected $description = 'Mark active courses with past expires_at as expired';

    public function handle(CourseService $service): int
    {
        $branch = $this->option('branch') ? (int) $this->option('branch') : null;
        $count = $service->expireExpired($branch);
        $this->info("Expired {$count} courses".($branch ? " (branch={$branch})" : ''));

        return self::SUCCESS;
    }
}
