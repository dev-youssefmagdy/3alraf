<?php

namespace App\Services\Growth;

use App\Models\Bundle;
use App\Models\OrderItem;
use App\Models\SeasonalEvent;
use App\Models\SeasonalMemory;
use App\Models\SeasonalTask;
use App\Models\TenantProduct;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Egyptian seasonal commerce calendar (spec 14). Hijri-based dates (Ramadan, Eids) are computed with the tabular Islamic calendar
 * (can be 1 day off the moon sighting, so they are EDITABLE); fixed dates are Gregorian rules. Readiness = inventory + lead time + margin per mapped category.
 */
class SeasonalCalendarService
{
    /** key => [name_ar, name_en, rule]. Rules: ['hijri', month, day, length] | ['fixed', month, day, length] | ['lastfriday', month] */
    public const DEFAULTS = [
        'ramadan' => ['رمضان', 'Ramadan', ['hijri', 9, 1, 30]],
        'eid_fitr' => ['عيد الفطر', 'Eid al-Fitr', ['hijri', 10, 1, 3]],
        'eid_adha' => ['عيد الأضحى', 'Eid al-Adha', ['hijri', 12, 10, 4]],
        'valentine' => ['عيد الحب', 'Valentine\'s Day', ['fixed', 2, 14, 1]],
        'mothers_day' => ['عيد الأم', 'Mother\'s Day', ['fixed', 3, 21, 1]],
        'wedding_season' => ['موسم الأفراح', 'Wedding season', ['fixed', 6, 1, 122]],
        'back_to_school' => ['العودة للمدارس', 'Back to school', ['fixed', 9, 1, 30]],
        'white_friday' => ['الجمعة البيضاء', 'White Friday / Black Friday', ['lastfriday', 11]],
    ];

    public const TASKS = [
        'inventory' => ['Review inventory and supplier lead time', 1.0], 'margin' => ['Review margins and offers', 0.85], 'bundles' => ['Propose and approve seasonal bundles', 0.75],
        'content' => ['Prepare content and creative assets', 0.6], 'review' => ['Review offers and creatives', 0.3], 'schedule' => ['Schedule publishing (manual tasks in Content calendar)', 0.15], 'post_review' => ['Post-season review and lessons', -1],
    ];

    public function __construct(private ActionLogger $log, private MetricsService $metrics, private BundleMetricsService $bundleMetrics, private BundleProposalService $proposals) {}

    // ---- Hijri (tabular civil calendar) ---------------------------------------------

    public static function hijriOf(Carbon $d): array
    {
        $jd = (int) floor(self::jd($d->year, $d->month, $d->day) + 0.5) + 0.5;
        $y = (int) floor((30 * ($jd - 1948439.5) + 10646) / 10631);
        $m = min(12, (int) ceil(($jd - (29 + self::islamicJd($y, 1, 1))) / 29.5) + 1);
        $day = (int) ($jd - self::islamicJd($y, $m, 1) + 1);

        return [$y, $m, $day];
    }

    private static function jd(int $y, int $m, int $d): float
    {
        if ($m <= 2) {
            $y--;
            $m += 12;
        }
        $a = intdiv($y, 100);
        $b = 2 - $a + intdiv($a, 4);

        return floor(365.25 * ($y + 4716)) + floor(30.6001 * ($m + 1)) + $d + $b - 1524.5;
    }

    private static function islamicJd(int $y, int $m, int $d): float
    {
        return $d + ceil(29.5 * ($m - 1)) + ($y - 1) * 354 + floor((3 + 11 * $y) / 30) + 1948439.5 - 1;
    }

    /** First Gregorian date in `$year` whose Hijri month/day match (null when the year holds none). */
    public static function gregorianOfHijri(int $year, int $hm, int $hd): ?Carbon
    {
        for ($d = Carbon::create($year, 1, 1); $d->year === $year; $d->addDay()) {
            [, $m, $day] = self::hijriOf($d);
            if ($m === $hm && $day === $hd) {
                return $d->copy();
            }
        }

        return null;
    }

    // ---- events ------------------------------------------------------------------------

    /** Creates the standard events (+ task checklist) for a year; existing rows are left alone (idempotent, never overwrites edits). */
    public function ensureYear(int $year, ?int $adminId = null): int
    {
        $n = 0;
        foreach (self::DEFAULTS as $key => [$ar, $en, $rule]) {
            if (SeasonalEvent::where('key', $key)->where('year', $year)->exists()) {
                continue;
            }
            $start = match ($rule[0]) {
                'hijri' => self::gregorianOfHijri($year, $rule[1], $rule[2]),
                'fixed' => Carbon::create($year, $rule[1], $rule[2]),
                'lastfriday' => Carbon::create($year, $rule[1], 1)->endOfMonth()->startOfDay()->previous(Carbon::FRIDAY)->setTime(0, 0),
            };
            if (! $start) {
                continue;
            }
            $len = $rule[3] ?? 1;
            $e = SeasonalEvent::create(['key' => $key, 'year' => $year, 'name_ar' => $ar, 'name_en' => $en, 'starts_on' => $start->toDateString(), 'ends_on' => $start->copy()->addDays($len - 1)->toDateString(), 'hijri' => $rule[0] === 'hijri', 'lead_time_days' => 21]);
            $this->seedTasks($e);
            $n++;
        }
        $this->log->log('seasonal.year_ensured', $adminId ? 'admin' : 'system', $adminId, null, null, 'ok', ['year' => $year, 'created' => $n]);

        return $n;
    }

    private function seedTasks(SeasonalEvent $e): void
    {
        foreach (self::TASKS as $code => [$title, $f]) {
            $due = $f < 0 ? $e->ends_on->copy()->addDays(7) : $e->starts_on->copy()->subDays((int) round($e->lead_time_days * $f));
            SeasonalTask::create(['seasonal_event_id' => $e->id, 'code' => $code, 'title' => $title, 'due_on' => $due->toDateString()]);
        }
    }

    public function update(int $id, array $d, ?int $adminId): SeasonalEvent
    {
        $e = SeasonalEvent::findOrFail($id);
        if ($e->status === 'closed') {
            throw ValidationException::withMessages(['state' => __('growth_hub.seasonal.closed')]);
        }
        $cats = array_values(array_unique(array_map('intval', array_filter((array) ($d['category_ids'] ?? []), 'is_numeric'))));
        $e->update(['starts_on' => $d['starts_on'], 'ends_on' => $d['ends_on'], 'lead_time_days' => (int) $d['lead_time_days'], 'notes' => $d['notes'] ?? null, 'category_ids' => $cats ?: null]);
        $this->log->log('seasonal.updated', 'admin', $adminId, null, $e);

        return $e;
    }

    public function toggleTask(int $taskId, ?int $adminId): SeasonalTask
    {
        $t = SeasonalTask::findOrFail($taskId);
        $t->update(['done_at' => $t->done_at ? null : now()]);
        $this->log->log('seasonal.task', 'admin', $adminId, null, $t, 'ok', ['done' => (bool) $t->done_at]);

        return $t;
    }

    // ---- readiness ---------------------------------------------------------------------

    /** Checklist of explicit checks, each ok|warn|fail with evidence. Unknown data is "warn", never "ok". */
    public function readiness(SeasonalEvent $e): array
    {
        $checks = [];
        $days = (int) now()->startOfDay()->diffInDays($e->starts_on, false);
        $checks[] = ['key' => 'lead_time', 'status' => $days >= $e->lead_time_days ? 'ok' : ($days >= 0 ? 'warn' : 'fail'), 'evidence' => ['days_left' => $days, 'lead_time_days' => $e->lead_time_days]];
        $cats = (array) $e->category_ids;
        if (! $cats) {
            $checks[] = ['key' => 'categories', 'status' => 'fail', 'evidence' => ['mapped' => 0]];

            return ['checks' => $checks, 'ready' => false];
        }
        $vel = $this->metrics->velocityRows(now())->keyBy('product_id');
        $floor = (float) app(GrowthConfig::class)->get('min_margin_percent', 15);
        $tps = TenantProduct::with('product.categories')->where('is_active', true)->get()->filter(fn ($tp) => $tp->product && $tp->product->categories->pluck('id')->intersect($cats)->isNotEmpty());
        $inStock = $tps->filter(fn ($tp) => ($vel[$tp->product_id]['on_hand'] ?? 0) > 0);
        $checks[] = ['key' => 'products', 'status' => $inStock->count() >= 2 ? 'ok' : ($inStock->count() ? 'warn' : 'fail'), 'evidence' => ['mapped_products' => $tps->count(), 'in_stock' => $inStock->count()]];
        $costed = $inStock->filter(fn ($tp) => (float) $tp->cost > 0);
        $low = $costed->filter(function ($tp) use ($floor) {
            $p = (float) ($tp->sale_price ?: $tp->price);

            return $p > 0 && ($p - (float) $tp->cost) / $p * 100 < $floor;
        });
        $checks[] = ['key' => 'margin', 'status' => $inStock->isEmpty() ? 'warn' : ($costed->count() < $inStock->count() ? 'warn' : ($low->isEmpty() ? 'ok' : 'warn')), 'evidence' => ['missing_cost' => $inStock->count() - $costed->count(), 'below_floor' => $low->count(), 'floor_percent' => $floor]];
        // expected demand from the last 30 days of sales vs stock, over the season length (a rough cover check, not a forecast)
        $len = max((int) $e->starts_on->diffInDays($e->ends_on) + 1, 1);
        $short = $inStock->filter(fn ($tp) => ($vel[$tp->product_id]['daily_velocity'] ?? 0) * $len > ($vel[$tp->product_id]['on_hand'] ?? 0));
        $checks[] = ['key' => 'cover', 'status' => $short->isEmpty() ? 'ok' : 'warn', 'evidence' => ['products_short_for_season' => $short->count(), 'season_days' => $len]];
        $bundles = Bundle::count();
        $checks[] = ['key' => 'bundles', 'status' => $bundles > 0 ? 'ok' : 'warn', 'evidence' => ['bundles' => $bundles]];

        return ['checks' => $checks, 'ready' => collect($checks)->every(fn ($c) => $c['status'] === 'ok')];
    }

    public function proposeBundles(int $id, ?int $adminId): array
    {
        return $this->proposals->propose('seasonal_gift', ['event_id' => $id], $adminId);
    }

    // ---- seasonal memory -----------------------------------------------------------------

    /** Closes a finished season: stores computed performance + the owner's lessons (kept for the next cycle). */
    public function close(int $id, array $d, ?int $adminId): SeasonalMemory
    {
        $e = SeasonalEvent::findOrFail($id);
        if ($e->ends_on->isFuture()) {
            throw ValidationException::withMessages(['state' => __('growth_hub.seasonal.not_ended')]);
        }
        $m = SeasonalMemory::updateOrCreate(['seasonal_event_id' => $e->id], ['performance' => $this->performance($e), 'content_formats' => $d['content_formats'] ?? null, 'start_timing' => $d['start_timing'] ?? null,
            'objections' => $d['objections'] ?? null, 'lessons' => $d['lessons'] ?? null]);
        $e->update(['status' => 'closed']);
        $this->log->log('seasonal.closed', 'admin', $adminId, null, $e);

        return $m;
    }

    /** Best products and bundles of the season window (delivered orders; margin null when a cost is missing). */
    public function performance(SeasonalEvent $e): array
    {
        $from = $e->starts_on->copy()->startOfDay();
        $to = $e->ends_on->copy()->endOfDay();
        $products = collect($this->metrics->byProduct($from, $to, 200));
        $best = collect($products['rows'] ?? $products)->sortByDesc(fn ($r) => (float) ($r['revenue'] ?? 0))->take(5)->map(fn ($r) => ['product_id' => $r['product_id'] ?? null, 'units' => $r['units'] ?? null, 'revenue' => $r['revenue'] ?? null, 'contribution_margin' => $r['contribution_margin'] ?? null])->values()->all();
        $bundles = collect($this->metrics->bundles($from, $to))->sortByDesc(fn ($r) => (float) ($r['line_margin']['value'] ?? -INF))->take(3)->values()->all();
        $s = $this->metrics->summary($from, $to);

        return ['best_products' => $best, 'profitable_bundles' => $bundles, 'orders' => $s['orders_completed']['value'] ?? null, 'revenue' => $s['revenue']['value'] ?? null, 'aov' => $s['aov']['value'] ?? null];
    }

    /** Lessons of the previous year's same season, shown when planning the new one. */
    public function lastMemory(SeasonalEvent $e): ?SeasonalMemory
    {
        return SeasonalMemory::whereHas('event', fn ($q) => $q->where('key', $e->key)->where('year', '<', $e->year))->latest('id')->first();
    }
}
