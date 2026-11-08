<?php

namespace App\Services\Marketing;

use App\DTOs\Marketing\MarketingQuery;
use App\Models\MarketingSuggestion;
use Illuminate\Support\Facades\Route;

/** View model of the marketer dashboard (/marketer), built from MarketingAnalyticsService (same figures as the internal API). */
class MarketingDashboardService
{
    public function __construct(private MarketingAnalyticsService $analytics) {}

    public function build(MarketingQuery $q, bool $withProfit): array
    {
        $overview = $this->analytics->overview($q);
        $series = $this->analytics->salesSummary($q)['data']['periods'];
        $best = $this->analytics->bestSellers($q);
        $slow = $this->analytics->slowMoving($q);
        $low = $this->analytics->lowStock();

        return [
            'o' => $overview['data'],
            'warnings' => collect($overview['warnings'])->reject(fn ($w) => ! $withProfit && ($w['field'] ?? null) !== null && in_array($w['field'], ['cost', 'net_profit'], true))->values()->all(),
            'withProfit' => $withProfit,
            'chart' => ['type' => 'line', 'labels' => array_column($series, 'period'), 'datasets' => [
                ['label' => __('marketing.dash.gross_sales'), 'data' => array_map('floatval', array_column($series, 'gross_sales'))],
            ]],
            'best' => array_slice($best['data'], 0, 10),
            'slow' => array_slice($slow['data'], 0, 10),
            'low' => array_slice($low['data'], 0, 10), 'lowTotal' => count($low['data']),
            // P5: pending suggestions card appears once the Suggestions Center exists
            'pendingSuggestions' => Route::has('marketer.suggestions.index') && class_exists(MarketingSuggestion::class)
                ? MarketingSuggestion::query()->where('status', 'pending')->count() : null,
        ];
    }
}
