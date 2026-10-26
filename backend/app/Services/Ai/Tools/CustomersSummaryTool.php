<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AiContext;

/** Aggregates only: no customer identity ever leaves this tool. */
class CustomersSummaryTool extends ReportTool
{
    public function name(): string { return 'customers_summary'; }

    public function description(): string { return 'Aggregate customer figures for a period: buyers, new customers, repeat buyers, orders, spend, average spend. No individual customer data.'; }

    public function schema(): array
    {
        return $this->obj($this->periodSchema());
    }

    protected function run(array $a, AiContext $ctx): array
    {
        $f = $this->filter($a, $ctx);
        $rows = $this->reports()->run('customers', $f)['rows'];
        $buyers = count($rows);
        $spent = array_sum(array_map(fn ($r) => (float) $r['spent'], $rows));
        $repeat = count(array_filter($rows, fn ($r) => (int) ($r['prior_orders'] ?? 0) > 0));

        return ['from' => $f->from, 'to' => $f->to, 'currency' => $ctx->currency, 'registered_buyers' => $buyers, 'repeat_buyers' => $repeat, 'orders' => array_sum(array_map(fn ($r) => (int) $r['orders'], $rows)),
            'total_spent' => number_format($spent, 2, '.', ''), 'avg_spent_per_buyer' => $buyers ? number_format($spent / $buyers, 2, '.', '') : '0.00',
            'new_customers' => app(\App\Repositories\Contracts\ReportRepositoryInterface::class)->newCustomers($f), 'note' => 'Registered customers only (guest orders excluded); report is capped at the top 200 buyers.'];
    }
}
