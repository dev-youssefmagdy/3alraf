<?php

namespace App\Repositories\Eloquent;

use App\Enums\OrderStatus;
use App\Models\AiTrendSnapshot;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\TenantProduct;
use App\Repositories\Contracts\AiTrendRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiTrendRepository implements AiTrendRepositoryInterface
{
    public function velocity(int $days, ?int $categoryId = null): Collection
    {
        $tid = current_tenant()->id;
        $now = now();
        $cur = $now->copy()->subDays($days);
        $prev = $now->copy()->subDays($days * 2);
        $statuses = array_map(fn ($s) => $s->value, OrderStatus::salesStatuses());
        // net units / amounts: returned units are removed pro rata (CALCULATIONS.md "Top products")
        $net = '(oi.quantity - oi.returned_qty)';
        $rev = '((oi.line_subtotal - oi.line_discount) * (oi.quantity - oi.returned_qty) / oi.quantity)';

        $q = DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('oi.tenant_id', $tid)->where('o.tenant_id', $tid)->whereNull('o.deleted_at')->whereIn('o.status', $statuses)
            ->where('o.created_at', '>=', $prev)->where('o.created_at', '<=', $now)->whereNotNull('oi.product_id')->where('oi.quantity', '>', 0)
            ->groupBy('oi.product_id')
            ->selectRaw("oi.product_id,
                SUM(CASE WHEN o.created_at >= ? THEN $net ELSE 0 END) as qty,
                SUM(CASE WHEN o.created_at < ? THEN $net ELSE 0 END) as prev_qty,
                SUM(CASE WHEN o.created_at >= ? THEN $rev ELSE 0 END) as revenue,
                SUM(CASE WHEN o.created_at >= ? THEN oi.unit_cost * $net ELSE 0 END) as cost", [$cur, $cur, $cur, $cur]);
        if ($categoryId) {
            $q->whereIn('oi.product_id', DB::table('category_product')->where('category_id', $categoryId)->select('product_id'));
        }

        return $q->get();
    }

    public function availableByProduct(?array $productIds = null): array
    {
        $q = StockLevel::query()->selectRaw('product_id, SUM(on_hand - reserved) as av')->groupBy('product_id');
        if ($productIds !== null) {
            $q->whereIn('product_id', $productIds ?: [0]);
        }

        return $q->pluck('av', 'product_id')->map(fn ($v) => (int) $v)->all();
    }

    public function listedProducts(): Collection
    {
        return TenantProduct::query()->with('product')->get()->filter(fn ($tp) => $tp->product)->map(fn ($tp) => (object) [
            'product_id' => $tp->product_id, 'tp_id' => $tp->id, 'sku' => (string) $tp->product->sku, 'barcode' => (string) $tp->product->barcode,
            'names' => array_values(array_filter((array) $tp->product->getTranslations('name'))), 'cost' => (string) ($tp->cost ?? '0'), 'price' => (string) ($tp->price ?? '0'), 'active' => (bool) $tp->is_active,
        ])->values();
    }

    public function catalogMix(int $limit = 8): array
    {
        $ids = TenantProduct::query()->pluck('product_id');
        $cats = DB::table('category_product as cp')->join('categories as c', 'c.id', '=', 'cp.category_id')->whereIn('cp.product_id', $ids)->whereNull('c.deleted_at')
            ->groupBy('c.id')->orderByRaw('COUNT(*) desc')->limit($limit)->selectRaw('c.id')->pluck('c.id');
        $brands = Product::query()->whereIn('id', $ids)->whereNotNull('brand_id')->select('brand_id', DB::raw('COUNT(*) n'))->groupBy('brand_id')->orderByDesc('n')->limit($limit)->pluck('brand_id');

        return [
            'categories' => \App\Models\Category::query()->whereIn('id', $cats)->get()->map(fn ($c) => (string) $c->getTranslation('name', 'en'))->filter()->values()->all(),
            'brands' => \App\Models\Brand::query()->whereIn('id', $brands)->get()->map(fn ($b) => (string) $b->name)->filter()->values()->all(),
        ];
    }

    public function latestSnapshot(string $kind, ?string $country): ?AiTrendSnapshot
    {
        return AiTrendSnapshot::query()->where('kind', $kind)->where('country', $country)->orderByDesc('generated_at')->orderByDesc('id')->first();
    }

    public function snapshotByKey(string $kind, string $key): ?AiTrendSnapshot
    {
        return AiTrendSnapshot::query()->where('kind', $kind)->where('period', $key)->orderByDesc('generated_at')->orderByDesc('id')->first();
    }

    public function saveSnapshot(string $kind, ?string $country, ?string $period, array $items): AiTrendSnapshot
    {
        return AiTrendSnapshot::create(['kind' => $kind, 'country' => $country, 'period' => $period, 'items' => $items, 'generated_at' => now()]);
    }

    public function updateItems(AiTrendSnapshot $s, array $items): void
    {
        $s->update(['items' => $items]);
    }
}
