<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AiContext;

class LowStockTool extends ReportTool
{
    public function name(): string { return 'low_stock'; }

    public function description(): string { return 'Products/variants at or below the store low-stock threshold (available = on hand - reserved), lowest first, plus stock value.'; }

    public function schema(): array
    {
        return $this->obj(['limit' => ['type' => 'integer']]);
    }

    protected function run(array $a, AiContext $ctx): array
    {
        $r = $this->reports()->run('stock', $this->filter([], $ctx));
        $low = array_values(array_filter($r['rows'], fn ($x) => ($x['status'] ?? '') === __('reports.status.low')));
        usort($low, fn ($x, $y) => $x['available'] <=> $y['available']);

        return ['currency' => $ctx->currency, 'totals' => $this->cards($r), 'low_count' => count($low), 'items' => array_map(fn ($x) => [
            'product' => $this->data($x['product'], 100), 'sku' => $x['sku'], 'on_hand' => $x['on_hand'], 'reserved' => $x['reserved'], 'available' => $x['available'],
        ], array_slice($low, 0, $this->limit($a)))];
    }
}
