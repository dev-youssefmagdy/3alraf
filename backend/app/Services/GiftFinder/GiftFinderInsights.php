<?php

namespace App\Services\GiftFinder;

use App\Models\Bundle;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Read-only data for the owner's gift-finder follow-up (integration API, n8n): products and bundles still missing gift tags, and the dated
 * occasions coming up (config gift_finder.occasion_calendar). Never writes anything.
 */
class GiftFinderInsights
{
    /**
     * Active products without any gift occasion / recipient tag, and live bundles (active + in their schedule window) none of whose products carries such a tag
     * (bundles have no tags of their own: the finder reads the tags of their items).
     *
     * @return array{products:list<array>,bundles:list<array>,counts:array{products:int,bundles:int,active_products:int,active_bundles:int}}
     */
    public function untagged(int $limit = 100): array
    {
        $untagged = fn (Product $p) => ! (array) $p->gift_recipients && ! (array) $p->gift_occasions;
        $products = Product::where('is_active', true)->orderBy('id')->get(['id', 'name', 'sku', 'slug', 'gift_suitable', 'gift_recipients', 'gift_occasions']);
        $missing = $products->filter($untagged)->values();
        $bundles = Bundle::with('items.product:id,gift_recipients,gift_occasions')->live()->orderBy('id')->get();
        $bundlesMissing = $bundles->filter(fn (Bundle $b) => $b->items->every(fn ($i) => ! $i->product || $untagged($i->product)))->values();

        return [
            'products' => $missing->take($limit)->map(fn (Product $p) => ['id' => $p->id, 'name' => (string) $p->name, 'sku' => $p->sku, 'slug' => $p->slug, 'gift_suitable' => (bool) $p->gift_suitable])->all(),
            'bundles' => $bundlesMissing->take($limit)->map(fn (Bundle $b) => ['id' => $b->id, 'name' => (string) $b->name, 'slug' => $b->slug, 'items' => $b->items->count()])->all(),
            'counts' => ['products' => $missing->count(), 'bundles' => $bundlesMissing->count(), 'active_products' => $products->count(), 'active_bundles' => $bundles->count()],
        ];
    }

    /**
     * Dated occasions starting within the next $days days (today included), soonest first.
     *
     * @return list<array{key:string,date:string,days_left:int,approximate:bool,calendar:string}>
     */
    public function upcomingOccasions(int $days, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();
        $end = $today->addDays($days);
        $out = new Collection;
        foreach ((array) config('gift_finder.occasion_calendar') as $key => $def) {
            foreach ([$today->year, $today->year + 1, $today->year + 2] as $year) {
                foreach ($this->datesIn($def, $year) as $d) {
                    if ($d->betweenIncluded($today, $end)) {
                        $out->push(['key' => $key, 'date' => $d->toDateString(), 'days_left' => (int) $today->diffInDays($d), 'approximate' => isset($def['hijri']), 'calendar' => isset($def['hijri']) ? 'hijri' : 'gregorian']);
                    }
                }
            }
        }

        return $out->unique(fn ($o) => $o['key'].$o['date'])->sortBy([['date', 'asc'], ['key', 'asc']])->values()->all();
    }

    /** @return list<CarbonImmutable> Gregorian dates of the occasion in the given Gregorian year (a lunar one can occur twice). */
    private function datesIn(array $def, int $year): array
    {
        if (isset($def['gregorian'])) {
            [$m, $d] = array_map('intval', explode('-', $def['gregorian']));

            return [CarbonImmutable::create($year, $m, $d)->startOfDay()];
        }
        if (! isset($def['hijri']) || ! class_exists(\IntlCalendar::class)) {
            return []; // ext-intl missing: lunar occasions are skipped rather than guessed
        }
        [$hm, $hd] = $def['hijri'];
        $cal = \IntlCalendar::createInstance('UTC', 'en_US@calendar=islamic-umalqura');
        $cal->setTime(CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'UTC')->getTimestamp() * 1000);
        $hy = $cal->get(\IntlCalendar::FIELD_YEAR);
        $out = [];
        foreach ([$hy, $hy + 1] as $y) {
            $cal->clear();
            $cal->set($y, $hm - 1, $hd); // IntlCalendar months are 0-based
            $g = CarbonImmutable::createFromTimestampUTC((int) floor($cal->getTime() / 1000))->startOfDay();
            if ($g->year === $year) {
                $out[] = CarbonImmutable::create($g->year, $g->month, $g->day)->startOfDay();
            }
        }

        return $out;
    }
}
