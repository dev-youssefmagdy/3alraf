<?php

namespace App\Services\Orders;

use App\Models\Bundle;
use App\Models\StockLevel;
use App\Support\Money;

/**
 * Bundles (docs/CALCULATIONS.md "Bundles"): purchasability, stock availability and the cent-exact expansion of a bundle
 * line into ordinary component lines. Component prices/costs always come from PriceResolver (tenant scoped), never flash.
 */
class BundleResolver
{
    public function __construct(private PriceResolver $prices) {}

    /** Bundle of the current tenant that is active, inside its window and not deleted; null otherwise. */
    public function live(int $bundleId): ?Bundle
    {
        return Bundle::query()->with('items')->live()->find($bundleId);
    }

    /**
     * Resolved components, or null when the bundle is empty or ANY component is not purchasable for this tenant.
     *
     * @return array<int,array{item:array,per:int}>|null
     */
    public function components(Bundle $bundle): ?array
    {
        $out = [];
        foreach ($bundle->items as $bi) {
            $item = $this->prices->item($bi->product_id, $bi->product_variant_id);
            if (! $item) {
                return null;
            }
            $out[] = ['item' => $item, 'per' => max(1, (int) $bi->quantity)];
        }

        return $out ?: null;
    }

    /** Σ regular unit price × qty per bundle (what the pack would cost when bought separately). */
    public function regularTotal(array $components): string
    {
        $t = '0';
        foreach ($components as $c) {
            $t = Money::add($t, Money::mul($c['item']['price'], $c['per']));
        }

        return $t;
    }

    /** Branch-summed available units (active branches) of a product/variant. */
    public function stock(int $productId, int $variantId): int
    {
        return (int) StockLevel::where('product_id', $productId)->where('product_variant_id', $variantId)
            ->whereHas('branch', fn ($b) => $b->where('is_active', true))->selectRaw('COALESCE(SUM(on_hand - reserved),0) a')->value('a');
    }

    public function allowsBackorder(): bool
    {
        return (bool) (current_tenant()?->settings['allow_backorder'] ?? false);
    }

    /** Whole bundles that can be sold now: min floor(available/qty_per); null = unlimited (backorder allowed); 0 when not purchasable. */
    public function available(Bundle $bundle, ?array $components = null): ?int
    {
        $components ??= $this->components($bundle);
        if (! $components || ! $bundle->isLive()) {
            return 0;
        }
        if ($this->allowsBackorder()) {
            return null;
        }
        $min = null;
        foreach ($components as $c) {
            $n = intdiv(max(0, $this->stock($c['item']['product']->id, $c['item']['variant']?->id ?? 0)), $c['per']);
            $min = $min === null ? $n : min($min, $n);
        }

        return $min ?? 0;
    }

    /**
     * Expand $qty bundles into component lines with allocated, whole-cent unit prices (Σ line totals = price × qty exactly).
     * Lines carry bundle_id / bundle_name / bundle_group; no flash; eligible for discounts like any line.
     *
     * @param  array<int,array{item:array,per:int}>  $components
     * @return array<int,array<string,mixed>>
     */
    public function expand(Bundle $bundle, array $components, int $qty, string $group): array
    {
        $total = Money::mul((string) $bundle->price, $qty);
        $units = array_map(fn ($c) => $c['per'] * $qty, $components);
        $weights = [];
        foreach ($components as $k => $c) {
            $weights[$k] = bcmul($this->cents($c['item']['price']), (string) $units[$k], 0);
        }
        if (bccomp(array_sum_bc($weights), '0', 0) === 0) { // every regular price is 0: share by unit count
            $weights = array_map('strval', $units);
        }
        $shares = $this->allocate($total, $weights);

        $lines = [];
        foreach ($components as $k => $c) {
            foreach ($this->splitUnits($shares[$k], $units[$k]) as [$unitPrice, $n]) {
                $item = $c['item'];
                $lines[] = ['product_id' => $item['product']->id, 'variant_id' => $item['variant']?->id ?? 0, 'name' => $item['name'], 'sku' => $item['sku'], 'barcode' => $item['barcode'],
                    'unit_price' => $unitPrice, 'unit_cost' => $item['cost'], 'qty' => $n, 'flash' => null, 'eligible' => true,
                    'bundle_id' => $bundle->id, 'bundle_name' => (string) $bundle->name, 'bundle_group' => $group];
            }
        }

        return $lines;
    }

    /**
     * Proportional split of $total (money string) by integer $weights: every share but the last is round-half-up of
     * total × w / W, clamped to what is left; the last takes the exact remainder. Σ = total, none negative.
     *
     * @param  array<int,string>  $weights
     * @return array<int,string> money strings
     */
    public function allocate(string $total, array $weights): array
    {
        $keys = array_keys($weights);
        $last = end($keys);
        $T = $this->cents($total);
        $W = array_sum_bc($weights);
        $left = $T;
        $out = [];
        foreach ($weights as $k => $w) {
            if ($k === $last) {
                $s = $left;
            } else {
                $s = bccomp($W, '0', 0) > 0 ? bcdiv(bcadd(bcmul(bcmul($T, '2', 0), $w, 0), $W, 0), bcmul($W, '2', 0), 0) : '0';
                $s = bccomp($s, $left, 0) > 0 ? $left : $s;
            }
            $left = bcsub($left, $s, 0);
            $out[$k] = bcdiv($s, '100', 2);
        }

        return $out;
    }

    /**
     * A share over $n units as at most two whole-cent unit prices: r units at base+1 cent, the rest at base cents
     * (so unit_price × qty reproduces the share exactly).
     *
     * @return array<int,array{0:string,1:int}> [unit_price, qty]
     */
    public function splitUnits(string $share, int $n): array
    {
        $c = $this->cents($share);
        $base = bcdiv($c, (string) $n, 0);
        $r = (int) bcsub($c, bcmul($base, (string) $n, 0), 0);
        $out = [];
        if ($n - $r > 0) {
            $out[] = [bcdiv($base, '100', 2), $n - $r];
        }
        if ($r > 0) {
            $out[] = [bcdiv(bcadd($base, '1', 0), '100', 2), $r];
        }

        return $out;
    }

    private function cents(string $money): string
    {
        return bcmul(Money::round($money), '100', 0);
    }
}
