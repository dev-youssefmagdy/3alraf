<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AiContext;

class SalesSummaryTool extends ReportTool
{
    private const GROUPS = ['day' => ['sales', ['day', 'orders', 'total', 'net_profit']], 'product' => ['products', ['product', 'orders', 'qty', 'revenue', 'profit']],
        'category' => ['categories', ['category', 'orders', 'qty', 'revenue', 'profit']], 'payment_method' => ['payment-methods', null]];

    public function name(): string { return 'sales_summary'; }

    public function description(): string { return 'Sales totals (orders, sales, costs, discounts, shipping, fees, net profit) for a period, optionally grouped by day, product, category or payment_method. Uses the official reports math.'; }

    public function schema(): array
    {
        return $this->obj($this->periodSchema() + ['group_by' => ['type' => 'string', 'enum' => array_keys(self::GROUPS)]]);
    }

    protected function run(array $a, AiContext $ctx): array
    {
        $f = $this->filter($a, $ctx);
        $sales = $this->reports()->run('sales', $f);
        $out = ['from' => $f->from, 'to' => $f->to, 'currency' => $ctx->currency, 'totals' => $this->cards($sales)];
        $g = self::GROUPS[$a['group_by'] ?? ''] ?? null;
        if ($g) {
            $r = $g[0] === 'sales' ? $sales : $this->reports()->run($g[0], $f);
            $out['group_by'] = $a['group_by'];
            $out['rows'] = $g[1] ? $this->rows($r, $g[1], 40) : array_slice($r['rows'], 0, 20);
        }

        return $out;
    }
}
