<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AiContext;

class TopSellersTool extends ReportTool
{
    public function name(): string { return 'top_sellers'; }

    public function description(): string { return 'Best-selling products (net of returns) in a period ranked by revenue, with units, revenue and profit.'; }

    public function schema(): array
    {
        return $this->obj($this->periodSchema() + ['limit' => ['type' => 'integer']]);
    }

    protected function run(array $a, AiContext $ctx): array
    {
        $f = $this->filter($a, $ctx);
        $r = $this->reports()->run('products', $f);
        $rows = array_map(fn ($x) => ['product' => $this->data($x['product'], 100)] + array_diff_key(array_intersect_key($x, array_flip(['orders', 'qty', 'revenue', 'profit'])), []), array_slice($r['rows'], 0, $this->limit($a)));

        return ['from' => $f->from, 'to' => $f->to, 'currency' => $ctx->currency, 'totals' => $this->cards($r), 'products' => $rows];
    }
}
