<?php

namespace App\Services\Ai\Generators;

use App\Enums\BannerPlacement;
use App\Enums\FlashDiscountType;
use App\Models\DiscountCode;
use App\Models\TenantProduct;
use App\Repositories\Contracts\AiGenerationRepositoryInterface;
use App\Services\Orders\PriceResolver;
use App\Services\Stock\StockItemCatalog;
use App\Support\SafeUrl;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Validates + normalises a generator draft against the same rules the real DTO/Request enforce, plus the AI-only business rules
 * (docs/CALCULATIONS.md: margin = (net price - cost) / net price, net = price without VAT when prices include tax; stock = branch-summed on_hand - reserved).
 * Pure function of (kind, draft, floor): safe to re-run on every edit and again at approval.
 */
class DraftValidator
{
    public const KINDS = ['banner', 'flash_sale', 'discount_code', 'bundle'];

    public function __construct(private PriceResolver $prices, private StockItemCatalog $catalog, private AiGenerationRepositoryInterface $repo) {}

    /** @return array{draft:array,errors:array,warnings:array,preview:array} */
    public function validate(string $kind, array $draft, float $floor): array
    {
        $r = ['errors' => [], 'warnings' => [], 'preview' => []];
        $d = match ($kind) {
            'banner' => $this->banner($draft, $r),
            'flash_sale' => $this->flash($draft, $floor, $r),
            'discount_code' => $this->code($draft, $floor, $r),
            'bundle' => $this->bundle($draft, $floor, $r),
            default => $draft,
        };

        return ['draft' => $d] + $r;
    }

    // ---- banner ---------------------------------------------------------------------------------------------------------------

    private function banner(array $in, array &$r): array
    {
        $p = BannerPlacement::tryFrom((string) ($in['placement'] ?? ''));
        if (! $p) {
            $this->err($r, 'placement');
        }
        $title = $this->translated($in['title'] ?? [], 120);
        if (! array_filter($title)) {
            $this->err($r, 'title_required');
        }
        $link = trim((string) ($in['link_url'] ?? ''));
        if ($link !== '' && ! SafeUrl::isSafe($link)) {
            $this->err($r, 'link_invalid');
            $link = '';
        }
        [$s, $e] = $this->period($in, $r, false);

        return ['placement' => $p?->value, 'title' => $title, 'link_url' => $link ?: null, 'starts_at' => $s, 'ends_at' => $e];
    }

    // ---- flash sale -----------------------------------------------------------------------------------------------------------

    private function flash(array $in, float $floor, array &$r): array
    {
        $name = $this->translated($in['name'] ?? [], 120);
        if (! array_filter($name)) {
            $this->err($r, 'name_required');
        }
        [$s, $e] = $this->period($in, $r, true);
        $items = [];
        $seen = [];
        foreach (array_slice((array) ($in['items'] ?? []), 0, 30) as $row) {
            $key = (string) ($row['item'] ?? '');
            $it = $this->item($key);
            if (! $it || isset($seen[$key])) {
                $this->err($r, $it ? 'item_duplicate' : 'item_invalid', ['item' => $key]);

                continue;
            }
            $seen[$key] = true;
            $type = FlashDiscountType::tryFrom((string) ($row['discount_type'] ?? ''));
            $val = is_numeric($row['value'] ?? null) ? (float) $row['value'] : -1;
            $qty = isset($row['qty_limit']) && $row['qty_limit'] !== '' && $row['qty_limit'] !== null ? (int) $row['qty_limit'] : null;
            if (! $type || $val <= 0 || ($type === FlashDiscountType::PERCENT && $val >= 100)) {
                $this->err($r, 'discount_invalid', ['name' => $it['name']]);

                continue;
            }
            $base = (float) $it['price'];
            $sell = $type === FlashDiscountType::PERCENT ? round($base * (1 - $val / 100), 2) : round($val, 2);
            $m = $this->margin($sell, (float) $it['cost']);
            if ($sell >= $base) {
                $this->err($r, 'no_discount', ['name' => $it['name']]);
            }
            $this->marginCheck($r, $it['name'], $m, $floor, (float) $it['cost']);
            $av = $this->available($key);
            if ($av !== null && $av <= 0) {
                $this->err($r, 'out_of_stock', ['name' => $it['name']]);
            } elseif ($av !== null && $qty !== null && $qty > $av) {
                $this->err($r, 'qty_over_stock', ['name' => $it['name'], 'qty' => $qty, 'available' => $av]);
            }
            $items[] = ['item' => $key, 'discount_type' => $type->value, 'value' => number_format($val, 2, '.', ''), 'qty_limit' => $qty];
            $r['preview'][] = ['name' => $it['name'], 'price' => $base, 'sell' => $sell, 'cost' => (float) $it['cost'], 'margin' => $m, 'available' => $av, 'qty_limit' => $qty];
        }
        if (! $items) {
            $this->err($r, 'items_required');
        }

        return ['name' => $name, 'starts_at' => $s, 'ends_at' => $e, 'items' => $items];
    }

    // ---- discount code --------------------------------------------------------------------------------------------------------

    private function code(array $in, float $floor, array &$r): array
    {
        $code = strtoupper(trim((string) ($in['code'] ?? '')));
        if (! preg_match('/^[A-Z0-9_-]{4,20}$/', $code)) {
            $this->err($r, 'code_format');
        } elseif (DiscountCode::where('code', $code)->exists()) {
            $this->err($r, 'code_taken', ['code' => $code]);
        }
        $type = in_array($in['type'] ?? '', ['percent', 'fixed'], true) ? $in['type'] : 'percent';
        $val = is_numeric($in['value'] ?? null) ? (float) $in['value'] : 0;
        $min = is_numeric($in['min_order'] ?? null) ? max(0, (float) $in['min_order']) : 0;
        $maxD = is_numeric($in['max_discount'] ?? null) && (float) $in['max_discount'] > 0 ? (float) $in['max_discount'] : null;
        $scope = ($in['scope'] ?? 'all') === 'products' ? 'products' : 'all';
        $pids = [];
        if ($scope === 'products') {
            $pids = TenantProduct::whereIn('product_id', array_map('intval', (array) ($in['product_ids'] ?? [])))->where('is_active', true)->pluck('product_id')->all();
            if (! $pids) {
                $this->err($r, 'scope_products');
            }
        }
        if ($val <= 0 || ($type === 'percent' && $val > 100)) {
            $this->err($r, 'discount_invalid', ['name' => $code]);
        } else {
            [$headFrac, $headAbs, $n] = $this->headroom($floor, $scope === 'products' ? $pids : null);
            if ($n === 0) {
                $this->warn($r, 'margin_unverifiable');
            } elseif ($type === 'percent' && $val > $headFrac * 100 + 1e-9) {
                $this->err($r, 'code_margin', ['max' => number_format(max(0, floor($headFrac * 10000) / 100), 2), 'floor' => $floor]);
            } elseif ($type === 'fixed') {
                $cap = $min > 0 ? $min * $headFrac : $headAbs;
                if ($val > $cap + 1e-9) {
                    $this->err($r, 'code_margin_fixed', ['max' => number_format(max(0, $cap), 2), 'floor' => $floor]);
                }
            }
            $r['preview'][] = ['headroom_pct' => $n ? round($headFrac * 100, 2) : null, 'products' => $n];
        }
        [$s, $e] = $this->period($in, $r, false);
        $ul = isset($in['usage_limit']) && (int) $in['usage_limit'] > 0 ? (int) $in['usage_limit'] : null;
        $pl = isset($in['per_customer_limit']) && (int) $in['per_customer_limit'] > 0 ? (int) $in['per_customer_limit'] : null;

        return ['code' => $code, 'type' => $type, 'value' => number_format($val, 2, '.', ''), 'min_order' => number_format($min, 2, '.', ''), 'max_discount' => $maxD !== null ? number_format($maxD, 2, '.', '') : null,
            'usage_limit' => $ul, 'per_customer_limit' => $pl, 'starts_at' => $s, 'ends_at' => $e, 'scope' => $scope, 'product_ids' => $pids, 'free_shipping' => (bool) ($in['free_shipping'] ?? false)];
    }

    /**
     * Largest discount a code may give without any covered product falling under the margin floor.
     *
     * @return array{0:float,1:float,2:int} [fraction of price, absolute amount (cheapest product), products considered]
     */
    private function headroom(float $floor, ?array $pids): array
    {
        $q = TenantProduct::where('is_active', true)->where('cost', '>', 0);
        if ($pids !== null) {
            $q->whereIn('product_id', $pids);
        }
        $frac = 1.0;
        $abs = PHP_FLOAT_MAX;
        $n = 0;
        foreach ($q->select(['price', 'sale_price', 'cost'])->cursor() as $tp) {
            $p = $this->net((float) ($tp->sale_price ?? $tp->price));
            $c = (float) $tp->cost;
            if ($p <= 0) {
                continue;
            }
            $min = $c / (1 - $floor / 100);           // lowest net price that still honours the floor
            $frac = min($frac, max(0, 1 - $min / $p));
            $abs = min($abs, max(0, $p - $min));
            $n++;
        }

        return [$frac, $n ? $abs : 0.0, $n];
    }

    // ---- bundle ---------------------------------------------------------------------------------------------------------------

    private function bundle(array $in, float $floor, array &$r): array
    {
        $name = $this->translated($in['name'] ?? [], 120);
        if (! array_filter($name)) {
            $this->err($r, 'name_required');
        }
        $desc = $this->translated($in['description'] ?? [], 1000);
        [$s, $e] = $this->period($in, $r, false);
        $items = [];
        $sumPrice = $sumCost = 0.0;
        $demand = [];
        foreach (array_slice((array) ($in['items'] ?? []), 0, 12) as $row) {
            $key = (string) ($row['item'] ?? '');
            $it = $this->item($key);
            $qty = max(1, min(99, (int) ($row['quantity'] ?? 1)));
            if (! $it) {
                $this->err($r, 'item_invalid', ['item' => $key]);

                continue;
            }
            $demand[$key] = ($demand[$key] ?? 0) + $qty;
            $sumPrice += (float) $it['price'] * $qty;
            $sumCost += (float) $it['cost'] * $qty;
            $items[$key] = ['item' => $key, 'quantity' => $demand[$key]];
            $r['preview'][] = ['name' => $it['name'], 'quantity' => $qty, 'price' => (float) $it['price'], 'cost' => (float) $it['cost']];
        }
        foreach ($demand as $key => $q) {
            $av = $this->available($key);
            if ($av !== null && $q > $av) {
                $this->err($r, 'qty_over_stock', ['name' => $this->item($key)['name'], 'qty' => $q, 'available' => $av]);
            }
        }
        if (count($items) < 2) {
            $this->err($r, 'bundle_min_items');
        }
        $price = is_numeric($in['price'] ?? null) ? round((float) $in['price'], 2) : 0.0;
        if ($price <= 0) {
            $this->err($r, 'price_invalid');
        } elseif ($items) {
            $m = $this->margin($price, $sumCost);
            $this->marginCheck($r, $name[current_tenant()->default_locale] ?? (array_values(array_filter($name))[0] ?? ''), $m, $floor, $sumCost);
            if ($price >= $sumPrice) {
                $this->warn($r, 'no_saving');
            }
            $r['summary'] = ['sum_price' => round($sumPrice, 2), 'sum_cost' => round($sumCost, 2), 'price' => $price, 'margin' => $m, 'saving' => round($sumPrice - $price, 2)];
        }

        return ['name' => $name, 'description' => $desc, 'price' => number_format($price, 2, '.', ''), 'starts_at' => $s, 'ends_at' => $e, 'items' => array_values($items)];
    }

    // ---- helpers --------------------------------------------------------------------------------------------------------------

    private function item(string $key): ?array
    {
        if (! preg_match('/^\d+-\d+$/', $key) || ! $this->catalog->exists($key)) {
            return null;
        }
        [$pid, $vid] = $this->catalog->split($key);

        return $this->prices->item($pid, $vid);   // tenant scoped, active + listed only
    }

    /** Branch-summed on_hand - reserved, null = unlimited (backorder allowed). */
    private function available(string $key): ?int
    {
        if (current_tenant()?->settings['allow_backorder'] ?? false) {
            return null;
        }
        [$pid, $vid] = $this->catalog->split($key);

        return $this->repo->available($pid, $vid);
    }

    private function net(float $price): float
    {
        $s = (array) current_tenant()->settings;
        $rate = (float) ($s['tax_rate'] ?? 0);

        return ! empty($s['prices_include_tax']) && $rate > 0 ? $price / (1 + $rate / 100) : $price;
    }

    private function margin(float $sell, float $cost): ?float
    {
        $n = $this->net($sell);

        return $n > 0 ? round(($n - $cost) / $n * 100, 2) : null;
    }

    private function marginCheck(array &$r, string $name, ?float $m, float $floor, float $cost): void
    {
        if ($cost <= 0) {
            $this->warn($r, 'cost_missing', ['name' => $name]);
        } elseif ($m === null || $m + 1e-9 < $floor) {
            $this->err($r, 'margin_floor', ['name' => $name, 'margin' => $m === null ? '0' : number_format($m, 2), 'floor' => $floor]);
        }
    }

    /** @return array{0:?string,1:?string} Y-m-d H:i:s in the tenant timezone */
    private function period(array $in, array &$r, bool $required): array
    {
        $tz = current_tenant()->timezone ?: config('app.timezone');
        $parse = function ($v) use ($tz) {
            try {
                return $v ? Carbon::parse((string) $v, $tz) : null;
            } catch (\Throwable) {
                return false;
            }
        };
        $s = $parse($in['starts_at'] ?? null);
        $e = $parse($in['ends_at'] ?? null);
        if ($s === false || $e === false) {
            $this->err($r, 'date_invalid');

            return [null, null];
        }
        if ($required && (! $s || ! $e)) {
            $this->err($r, 'date_required');
        }
        if ($e && $e->isPast()) {
            $this->err($r, 'ends_past');
        }
        if ($s && $e && ! $e->gt($s)) {
            $this->err($r, 'date_order');
        }
        if ($s && $e && $s->diffInDays($e) > 366) {
            $this->warn($r, 'long_period');
        }

        return [$s?->format('Y-m-d H:i:s'), $e?->format('Y-m-d H:i:s')];
    }

    /** Tenant locales only, tags stripped, trimmed. */
    private function translated(mixed $v, int $max): array
    {
        $out = [];
        foreach ((array) current_tenant()->locales as $l) {
            $t = is_array($v) ? ($v[$l] ?? null) : null;
            $out[$l] = is_string($t) ? Str::limit(trim(strip_tags($t)), $max, '') : '';
        }

        return $out;
    }

    private function err(array &$r, string $code, array $params = []): void
    {
        $r['errors'][] = ['code' => $code, 'params' => $params];
    }

    private function warn(array &$r, string $code, array $params = []): void
    {
        $r['warnings'][] = ['code' => $code, 'params' => $params];
    }
}
