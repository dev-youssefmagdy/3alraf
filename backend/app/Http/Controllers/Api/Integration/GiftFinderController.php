<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Services\GiftFinder\GiftFinderInsights;
use App\Services\GiftFinder\GiftFinderService;
use App\Services\GiftFinder\GiftFinderSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gift-finder reads for the n8n owner reports (scope `read` or `gift_finder:read`). Aggregates and catalog data only: no customer data. */
class GiftFinderController extends Controller
{
    public function __construct(private GiftFinderSettings $settings) {}

    /** GET gift-finder/stats?days=30 (1..365): searches, completion / cart / order rates, gift-session orders, by occasion. */
    public function stats(Request $r, GiftFinderService $svc): JsonResponse
    {
        $days = $this->int($r, 'days', 30, 1, 365);

        $stats = $svc->stats($days);
        // always a JSON object {occasion: count} — an empty PHP array would serialise as [] and break consumers (n8n weekly report)
        $stats['by_occasion'] = (object) $stats['by_occasion'];

        return $this->data(['enabled' => $this->settings->enabled()] + $stats);
    }

    /** GET gift-finder/untagged?limit=100 (1..500): active products and live bundles with no gift occasion / recipient tag. */
    public function untagged(Request $r, GiftFinderInsights $insights): JsonResponse
    {
        return $this->data($insights->untagged($this->int($r, 'limit', 100, 1, 500)));
    }

    /** GET gift-finder/occasions?days=45 (1..366): dated occasions in the next N days; lunar (Hijri) ones flagged approximate. */
    public function occasions(Request $r, GiftFinderInsights $insights): JsonResponse
    {
        $days = $this->int($r, 'days', (int) config('gift_finder.occasions_default_days', 45), 1, (int) config('gift_finder.occasions_max_days', 366));
        $list = array_map(fn ($o) => $o + ['label' => ['ar' => __('gift_finder.occasions.'.$o['key'], [], 'ar'), 'en' => __('gift_finder.occasions.'.$o['key'], [], 'en')]], $insights->upcomingOccasions($days));

        return $this->data(['days' => $days, 'today' => now()->toDateString(), 'occasions' => $list]);
    }

    private function int(Request $r, string $key, int $default, int $min, int $max): int
    {
        $v = $r->query($key);
        if ($v === null || $v === '') {
            return $default;
        }
        abort_unless(is_string($v) && ctype_digit($v) && (int) $v >= $min && (int) $v <= $max, 422, "Query parameter {$key} must be an integer between {$min} and {$max}.");

        return (int) $v;
    }

    private function data(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
