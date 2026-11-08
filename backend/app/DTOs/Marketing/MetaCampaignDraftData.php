<?php

namespace App\DTOs\Marketing;

use App\Support\Marketing\CanvaUrl;
use App\Support\Marketing\InternalLink;
use App\Support\Marketing\StoreText;
use Carbon\CarbonImmutable;

/**
 * Normalised content of a Meta campaign draft (MCP.md §7). `content` holds exactly the columns that are hashed (MetaCampaignDraftService::hash)
 * and later sent to Meta: plain texts (untrusted AI input), sorted id sets, ISO countries upper-cased, money as "0.00", dates "Y-m-d H:i:s",
 * landing page reduced to a path on this store. product_urls are derived by the service from product_ids (never trusted from input).
 */
final readonly class MetaCampaignDraftData
{
    public function __construct(public array $content) {}

    public static function fromArray(array $v): self
    {
        $plain = fn ($x, int $max) => is_scalar($x) && ($s = trim((string) StoreText::plain((string) $x))) !== '' ? mb_substr($s, 0, $max) : null;
        $list = fn ($x, int $max, int $len) => array_values(array_unique(array_filter(array_map(fn ($i) => $plain($i, $len), array_slice((array) $x, 0, $max)))));
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($v['product_ids'] ?? [])), fn ($i) => $i > 0)));
        sort($ids);
        $assetIds = array_values(array_unique(array_filter(array_map('intval', (array) ($v['creative']['asset_ids'] ?? [])), fn ($i) => $i > 0)));
        $aud = (array) ($v['audience'] ?? []);
        $loc = (array) ($v['locations'] ?? []);
        $pl = (array) ($v['placements'] ?? []);
        $auto = ! array_key_exists('automatic', $pl) || filter_var($pl['automatic'], FILTER_VALIDATE_BOOL);
        $countries = array_values(array_unique(array_map('strtoupper', array_filter((array) ($loc['countries'] ?? []), fn ($c) => is_string($c) && preg_match('/^[A-Za-z]{2}$/', $c)))));
        if (! $countries) {
            $countries = [strtoupper((string) (setting('country') ?: 'EG'))];
        }
        $dt = function ($x) {
            if ($x === null || $x === '') {
                return null;
            }
            try {
                return CarbonImmutable::parse((string) $x)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        };
        $kpis = [];
        foreach (array_slice((array) ($v['expected_kpis'] ?? []), 0, 12, true) as $k => $val) {
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $k));
            if ($key !== '' && is_scalar($val) && ($p = $plain($val, 200)) !== null) {
                $kpis[$key] = $p;
            }
        }
        ksort($kpis);
        $objective = (string) ($v['objective'] ?? '');
        $goal = in_array($v['optimization_goal'] ?? null, (array) config('meta.optimization_goals'), true) ? $v['optimization_goal'] : (config('meta.objectives.'.$objective) ?: 'LINK_CLICKS');
        $sortList = function (array $a) {
            sort($a);

            return array_values(array_unique($a));
        };

        return new self([
            'name' => (string) $plain($v['name'] ?? '', 200),
            'objective' => $objective,
            'product_ids' => $ids,
            'audience' => [
                'description' => $plain($aud['description'] ?? null, 1000),
                'age_min' => max(13, min(65, (int) ($aud['age_min'] ?? 18) ?: 18)),
                'age_max' => max(13, min(65, (int) ($aud['age_max'] ?? 65) ?: 65)),
                'genders' => in_array($aud['genders'] ?? null, ['male', 'female'], true) ? $aud['genders'] : 'all',
                'interests' => $list($aud['interests'] ?? [], 25, 100),
            ],
            'locations' => ['countries' => $sortList($countries), 'cities' => $list($loc['cities'] ?? [], 50, 100)],
            'placements' => $auto ? ['automatic' => true, 'publisher_platforms' => [], 'facebook_positions' => [], 'instagram_positions' => []] : [
                'automatic' => false,
                'publisher_platforms' => $sortList(array_values(array_intersect((array) config('meta.publisher_platforms'), (array) ($pl['publisher_platforms'] ?? [])))),
                'facebook_positions' => $sortList(array_values(array_intersect((array) config('meta.positions.facebook'), (array) ($pl['facebook_positions'] ?? [])))),
                'instagram_positions' => $sortList(array_values(array_intersect((array) config('meta.positions.instagram'), (array) ($pl['instagram_positions'] ?? [])))),
            ],
            'budget_type' => ($v['budget_type'] ?? 'daily') === 'lifetime' ? 'lifetime' : 'daily',
            'budget_amount' => number_format(round((float) ($v['budget_amount'] ?? 0), 2), 2, '.', ''),
            'currency' => strtoupper((string) (($v['currency'] ?? null) ?: (config('meta.default_currency') ?: (setting('currency_code') ?: 'EGP')))),
            'start_at' => $dt($v['start_at'] ?? null),
            'end_at' => $dt($v['end_at'] ?? null),
            'creative' => ['asset_ids' => $assetIds, 'canva_url' => CanvaUrl::normalize($v['creative']['canva_url'] ?? null), 'notes' => $plain($v['creative']['notes'] ?? null, 2000)],
            'copy' => ['primary_text' => $plain($v['copy']['primary_text'] ?? null, 2000), 'headline' => $plain($v['copy']['headline'] ?? null, 255),
                'description' => $plain($v['copy']['description'] ?? null, 255)],
            'cta' => in_array($v['cta'] ?? null, (array) config('meta.ctas'), true) ? $v['cta'] : 'SHOP_NOW',
            'landing_url' => InternalLink::normalize(is_string($v['landing_url'] ?? null) ? $v['landing_url'] : null),
            'optimization_goal' => $goal,
            'expected_kpis' => $kpis,
            'risks' => $plain($v['risks'] ?? null, 3000),
            'assumptions' => $plain($v['assumptions'] ?? null, 3000),
        ]);
    }

    /** Back to the input shape (PATCH = merge the sent top-level keys into this, then fromArray again). */
    public static function inputOf(array $columns): array
    {
        return array_intersect_key($columns, array_flip(['name', 'objective', 'product_ids', 'audience', 'locations', 'placements', 'budget_type', 'budget_amount',
            'currency', 'start_at', 'end_at', 'creative', 'copy', 'cta', 'landing_url', 'optimization_goal', 'expected_kpis', 'risks', 'assumptions']));
    }
}
