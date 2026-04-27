<?php

namespace App\Console\Commands;

use App\Models\StockLevel;
use App\Services\ExpiryService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class InventoryExpiryScan extends Command
{
    protected $signature = 'inventory:expiry-scan {--branch= : Filter by branch_id} {--notify : Write notifications for red/expired lots}';

    protected $description = 'Classify stock lots into expiry buckets and print a summary; optionally write notifications.';

    public function handle(ExpiryService $expiry, NotificationService $notifications): int
    {
        $q = StockLevel::query()
            ->with(['product', 'warehouse'])
            ->where('quantity', '>', 0);

        if ($branch = $this->option('branch')) {
            $q->whereHas('warehouse', fn ($w) => $w->where('branch_id', (int) $branch));
        }

        $buckets = [
            ExpiryService::EXPIRED => 0,
            ExpiryService::RED => 0,
            ExpiryService::ORANGE => 0,
            ExpiryService::YELLOW => 0,
            ExpiryService::GREEN => 0,
        ];

        $rows = [];
        $alertRows = [];
        $q->chunk(500, function ($levels) use ($expiry, &$buckets, &$rows, &$alertRows) {
            foreach ($levels as $level) {
                $bucket = $expiry->classifyLevel($level);
                $buckets[$bucket]++;
                if (in_array($bucket, [ExpiryService::EXPIRED, ExpiryService::RED, ExpiryService::ORANGE], true)) {
                    $row = [
                        'product' => $level->product?->name ?? '-',
                        'warehouse' => $level->warehouse?->name ?? '-',
                        'lot_no' => $level->lot_no ?? '-',
                        'expiry' => optional($level->expiry_date)->toDateString() ?? '-',
                        'qty' => $level->quantity,
                        'bucket' => $bucket,
                    ];
                    $rows[] = $row;
                    if (in_array($bucket, [ExpiryService::EXPIRED, ExpiryService::RED], true)) {
                        $alertRows[] = ['level' => $level, 'bucket' => $bucket];
                    }
                }
            }
        });

        $this->info('Expiry scan summary:');
        foreach ($buckets as $k => $v) {
            $this->line(sprintf('  %-8s %d', $k, $v));
        }

        if (! empty($rows)) {
            $this->newLine();
            $this->table(['Product', 'Warehouse', 'Lot', 'Expiry', 'Qty', 'Bucket'], $rows);
        }

        if ($this->option('notify') && ! empty($alertRows)) {
            $written = 0;
            foreach ($alertRows as $r) {
                $level = $r['level'];
                $branchId = $level->warehouse?->branch_id;
                if (! $branchId) {
                    continue;
                }
                $severity = $r['bucket'] === ExpiryService::EXPIRED ? 'critical' : 'warning';
                $title = $r['bucket'] === ExpiryService::EXPIRED
                    ? "ยา/สินค้าหมดอายุ: {$level->product?->name}"
                    : "ยา/สินค้าใกล้หมดอายุ (<30 วัน): {$level->product?->name}";
                $body = "Lot {$level->lot_no} หมดอายุ {$level->expiry_date?->toDateString()} • คลัง {$level->warehouse?->name} • คงเหลือ {$level->quantity}";
                $notifications->writeToRole(
                    roleName: 'pharmacist',
                    branchId: $branchId,
                    type: 'expiry_alert',
                    title: $title,
                    body: $body,
                    severity: $severity,
                    channel: $r['bucket'] === ExpiryService::EXPIRED ? 'line' : 'in_app',
                    relatedType: 'stock_level',
                    relatedId: $level->id,
                    data: ['bucket' => $r['bucket']],
                );
                $notifications->writeToRole(
                    roleName: 'branch_admin',
                    branchId: $branchId,
                    type: 'expiry_alert',
                    title: $title,
                    body: $body,
                    severity: $severity,
                    channel: 'in_app',
                    relatedType: 'stock_level',
                    relatedId: $level->id,
                    data: ['bucket' => $r['bucket']],
                );
                $written++;
            }
            $this->newLine();
            $this->info("Notified for {$written} red/expired lot(s)");
            $sent = $notifications->dispatchPending(500);
            $this->info("Dispatched {$sent} pending notifications");
        }

        return self::SUCCESS;
    }
}
