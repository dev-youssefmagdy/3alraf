<?php

namespace App\Console\Commands;

use App\Models\StockLevel;
use App\Services\Growth\GrowthConfig;
use App\Services\Growth\GrowthEventService;
use App\Services\Growth\GrowthFeatures;
use Illuminate\Console\Command;

class GrowthScanLowStock extends Command
{
    protected $signature = 'growth:scan-low-stock';

    protected $description = 'Safety net: emit stock.low for stock at/below the threshold (once per product/day, shared with the model-event path)';

    public function handle(GrowthFeatures $features, GrowthConfig $config, GrowthEventService $events): int
    {
        if (! $features->active('events_outbox')) {
            $this->info('skipped: events_outbox is off');

            return self::SUCCESS;
        }
        $threshold = (int) $config->get('low_stock_threshold', 5);
        $n = 0;
        StockLevel::query()->whereRaw('(on_hand - reserved) <= ?', [$threshold])->orderBy('id')->chunkById(500, function ($rows) use ($events, $threshold, &$n) {
            foreach ($rows as $s) {
                $d = ['product_id' => $s->product_id, 'variant_id' => $s->product_variant_id ?: null, 'branch_id' => $s->branch_id, 'available' => $s->on_hand - $s->reserved, 'threshold' => $threshold];
                $events->emitOnce(GrowthEventService::lowStockKey($d), 'stock.low', $d) && $n++;
            }
        });
        $this->info("$n stock.low");

        return self::SUCCESS;
    }
}
