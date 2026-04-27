<?php

namespace App\Console\Commands;

use App\Models\BroadcastCampaign;
use App\Services\BroadcastService;
use Illuminate\Console\Command;

class CrmDispatchDue extends Command
{
    protected $signature = 'crm:dispatch-due {--branch= : Optional branch filter}';

    protected $description = 'Send all scheduled broadcast campaigns whose time has come.';

    public function handle(BroadcastService $broadcasts): int
    {
        $q = BroadcastCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());

        if ($branch = $this->option('branch')) {
            $q->where('branch_id', (int) $branch);
        }

        $campaigns = $q->with(['segment', 'template'])->limit(50)->get();
        if ($campaigns->isEmpty()) {
            $this->info('No campaigns due.');

            return self::SUCCESS;
        }

        foreach ($campaigns as $c) {
            $this->line("→ Sending campaign #{$c->id} ({$c->name}) ...");

            try {
                $broadcasts->sendNow($c);
                $this->info("  ✓ sent={$c->sent_count} failed={$c->failed_count} skipped={$c->skipped_count}");
            } catch (\Throwable $e) {
                $c->status = 'failed';
                $c->save();
                $this->error("  ✗ {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
