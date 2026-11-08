<?php

namespace App\Services\Marketing;

/**
 * Campaign-candidate score (0..100) of a product for the date range (docs/marketing/API_DOCUMENTATION.md §campaign-candidates).
 *
 *   score = Wv·velocity + Wm·margin + Ws·stock_cover + Wr·recency          (weights config marketing.campaign.weights, default 35/30/20/15)
 *
 *   velocity    = units_sold / max_units_sold among the eligible products of the same request (0 when nobody sold anything)
 *   margin      = clamp(margin% / margin_full_percent, 0, 1); margin% = (effective price − listing cost) / effective price × 100.
 *                 Listing cost missing (null or 0) ⇒ margin = 0 and a `cost_missing` data_completeness warning (cost is never invented).
 *   stock_cover = units sold > 0: clamp((available / (units_sold / range_days)) / stock_cover_full_days, 0, 1)
 *                 no sales:       clamp(available / stock_cover_no_sales_units, 0, 1)
 *   recency     = last sale inside the range d days before `to`: 1 when d ≤ recency_full_days, else
 *                 max(0, 1 − (d − recency_full_days) / max(1, range_days − recency_full_days)); no sale in the range ⇒ 0.
 *
 * Eligible: active, priced, not deleted, available ≥ campaign.min_available. Order: score desc, available desc, product id asc (deterministic).
 * Each component is rounded to 4 decimals, the score to 2.
 */
class CampaignScorer
{
    /**
     * @param  array{units:int,price:?string,cost:?string,available:int,days_since_last_sale:?int}  $p
     * @return array{score:float,components:array<string,float>,margin_percent:?float,stock_cover_days:?float,warnings:list<array>}
     */
    public function score(array $p, int $maxUnits, int $rangeDays): array
    {
        $cfg = (array) config('marketing.campaign');
        $w = (array) ($cfg['weights'] ?? []);
        $warnings = [];

        $velocity = $maxUnits > 0 ? min(1, $p['units'] / $maxUnits) : 0.0;

        $marginPct = null;
        $price = $p['price'] !== null ? (float) $p['price'] : 0.0;
        $cost = $p['cost'] !== null ? (float) $p['cost'] : 0.0;
        if ($price <= 0) {
            $warnings[] = ['code' => 'missing_data', 'field' => 'price', 'message' => 'No selling price: margin cannot be computed.'];
        } elseif ($cost <= 0) {
            $warnings[] = ['code' => 'missing_data', 'field' => 'cost', 'message' => 'No purchase cost recorded: margin component set to 0 (not estimated).'];
        } else {
            $marginPct = round(($price - $cost) / $price * 100, 2);
        }
        $margin = $marginPct === null ? 0.0 : max(0, min(1, $marginPct / max(1, (float) ($cfg['margin_full_percent'] ?? 50))));

        $cover = null;
        if ($p['units'] > 0) {
            $cover = round($p['available'] / ($p['units'] / max(1, $rangeDays)), 1);
            $stockCover = max(0, min(1, $cover / max(1, (float) ($cfg['stock_cover_full_days'] ?? 60))));
        } else {
            $stockCover = max(0, min(1, $p['available'] / max(1, (float) ($cfg['stock_cover_no_sales_units'] ?? 10))));
        }

        $full = (int) ($cfg['recency_full_days'] ?? 7);
        $d = $p['days_since_last_sale'];
        $recency = $d === null ? 0.0 : ($d <= $full ? 1.0 : max(0, 1 - ($d - $full) / max(1, $rangeDays - $full)));

        $c = ['velocity' => round($velocity, 4), 'margin' => round($margin, 4), 'stock_cover' => round($stockCover, 4), 'recency' => round($recency, 4)];
        $score = 0.0;
        foreach ($c as $k => $v) {
            $score += (float) ($w[$k] ?? 0) * $v;
        }

        return ['score' => round($score, 2), 'components' => $c, 'margin_percent' => $marginPct, 'stock_cover_days' => $cover, 'warnings' => $warnings];
    }

    /** Deterministic order: score desc, available desc, id asc. */
    public function sort(array $rows): array
    {
        usort($rows, fn ($a, $b) => [$b['score'], $b['stock']['available'], $a['product_id']] <=> [$a['score'], $a['stock']['available'], $b['product_id']]);

        return $rows;
    }

    /** {en, ar} one-line explanation built only from the numbers above. */
    public function explain(array $p, array $r, int $rangeDays): array
    {
        $m = $r['margin_percent'] === null ? ['en' => 'margin unknown (no cost)', 'ar' => 'الهامش غير معروف (لا توجد تكلفة)']
            : ['en' => "margin {$r['margin_percent']}%", 'ar' => "هامش {$r['margin_percent']}%"];
        $cv = $r['stock_cover_days'] === null ? ['en' => "{$p['available']} units in stock, no sales yet", 'ar' => "{$p['available']} قطعة متاحة ولا مبيعات بعد"]
            : ['en' => "{$p['available']} units ≈ {$r['stock_cover_days']} days of cover", 'ar' => "{$p['available']} قطعة ≈ تغطية {$r['stock_cover_days']} يوم"];
        $rc = $p['days_since_last_sale'] === null ? ['en' => 'no sale in the range', 'ar' => 'لا مبيعات في الفترة']
            : ['en' => "last sale {$p['days_since_last_sale']} day(s) ago", 'ar' => "آخر بيع منذ {$p['days_since_last_sale']} يوم"];

        return [
            'en' => "Score {$r['score']}/100: sold {$p['units']} units in {$rangeDays} days, {$m['en']}, {$cv['en']}, {$rc['en']}.",
            'ar' => "الدرجة {$r['score']}/100: بيع {$p['units']} قطعة خلال {$rangeDays} يوم، {$m['ar']}، {$cv['ar']}، {$rc['ar']}.",
        ];
    }
}
