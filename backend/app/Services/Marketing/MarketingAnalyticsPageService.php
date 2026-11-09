<?php

namespace App\Services\Marketing;

use App\DTOs\Marketing\MarketingQuery;
use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;

/** View model of /marketer/analytics, built only from MarketingAnalyticsService so the figures match the dashboard and the internal API. */
class MarketingAnalyticsPageService
{
    public function __construct(private MarketingAnalyticsService $analytics) {}

    public function build(MarketingQuery $q): array
    {
        $overview = $this->analytics->overview($q);
        $orders = $this->analytics->ordersSummary($q)['data'];
        $metrics = $this->analytics->marketingMetrics($q)['data'];
        $periods = $this->fillGaps($q, $this->analytics->salesSummary($q)['data']['periods']);
        $labels = array_column($periods, 'period');

        return [
            'o' => $overview['data'],
            'warnings' => $overview['warnings'],
            'periods' => $periods,
            'salesChart' => ['type' => 'line', 'labels' => $labels, 'datasets' => [
                ['label' => __('marketing.dash.gross_sales'), 'data' => array_map('floatval', array_column($periods, 'gross_sales'))],
            ]],
            'ordersChart' => ['type' => 'bar', 'integer' => true, 'labels' => $labels, 'datasets' => [
                ['label' => __('marketing.dash.orders'), 'data' => array_map('intval', array_column($periods, 'orders'))],
            ]],
            'orders' => $orders,
            'statuses' => $this->statusRows($orders['by_status'], (int) $orders['orders_total']),
            'channels' => $this->shareRows((array) $orders['by_channel']),
            'metrics' => $metrics,
            'candidates' => array_slice($this->analytics->campaignCandidates($q)['data'], 0, 10),
            'best' => array_slice($this->analytics->bestSellers($q)['data'], 0, 10),
        ];
    }

    /** A chart must show days without orders as zero, so every bucket of the range is present and in order. */
    private function fillGaps(MarketingQuery $q, array $periods): array
    {
        $by = (string) $q->filter('group_by', 'day');
        $known = array_column($periods, null, 'period');
        $to = CarbonImmutable::parse($q->to);
        $out = [];
        for ($d = CarbonImmutable::parse($q->from); $d <= $to; $d = $d->addDay()) {
            $key = MarketingAnalyticsService::periodKey($d, $by);
            $out[$key] ??= $known[$key] ?? ['period' => $key, 'orders' => 0, 'gross_sales' => '0.00', 'discounts' => '0.00', 'aov' => null];
        }

        return array_values($out);
    }

    private function statusRows(array $counts, int $total): array
    {
        $rows = [];
        foreach (OrderStatus::cases() as $s) {
            if (($c = (int) ($counts[$s->value] ?? 0)) > 0) {
                $rows[] = ['label' => $s->label(), 'count' => $c, 'share' => $total ? round($c / $total * 100, 1) : 0.0];
            }
        }

        return $rows;
    }

    private function shareRows(array $counts): array
    {
        $total = array_sum($counts);

        return array_map(fn ($k, $c) => ['key' => (string) $k, 'count' => (int) $c, 'share' => $total ? round($c / $total * 100, 1) : 0.0], array_keys($counts), $counts);
    }
}
