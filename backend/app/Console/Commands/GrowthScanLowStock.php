<?php

namespace App\Console\Commands;

use App\Models\StockLevel;
use App\Services\Growth\GrowthConfig;
use App\Services\Growth\GrowthEventService;
use App\Services\Growth\GrowthFeatures;
use Illuminate\Console\Command;

class GrowthScanLowStock extends Command
{
    protected $signature = 'growth:scan-low-stock {--tenant= : Tenant slug}';

    protected $description = 'Safety net: emit stock.low for stock at/below the threshold (once per product/day, shared with the model-event path)';

    public function handle(GrowthFeatures $features, GrowthConfig $config, GrowthEventService $events): int
    {
        $prev = app()->bound('currentTenant') ? app('currentTenant') : null;
        foreach ($features->tenantsWith('events_outbox') as $t) {
            if ($this->option('tenant') && $t->slug !== $this->option('tenant')) {
                continue;
            }
            app()->instance('currentTenant', $t);
            $threshold = (int) $config->get('low_stock_threshold', 5, $t);
            $n = 0;
            StockLevel::withoutGlobalScopes()->where('tenant_id', $t->id)->whereRaw('(on_hand - reserved) <= ?', [$threshold])->orderBy('id')->chunkById(500, function ($rows) use ($events, $t, $threshold, &$n) {
                foreach ($rows as $s) {
                    $d = ['product_id' => $s->product_id, 'variant_id' => $s->product_variant_id ?: null, 'branch_id' => $s->branch_id, 'available' => $s->on_hand - $s->reserved, 'threshold' => $threshold];
                    $events->emitOnce(GrowthEventService::lowStockKey($d), 'stock.low', $d, $t) && $n++;
                }
            });
            $this->info("{$t->slug}: $n stock.low");
        }
        $prev ? app()->instance('currentTenant', $prev) : app()->forgetInstance('currentTenant');

        return self::SUCCESS;
    }
}
