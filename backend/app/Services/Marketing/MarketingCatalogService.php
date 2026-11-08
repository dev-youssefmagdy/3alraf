<?php

namespace App\Services\Marketing;

use App\DTOs\Marketing\MarketingQuery;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Contracts\MarketingRepositoryInterface;
use App\Services\Admin\LowStockService;
use App\Services\Orders\BundleResolver;
use App\Services\Orders\PriceResolver;
use App\Support\Marketing\MarketingResponse as R;
use App\Support\Marketing\StoreText;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Catalog & merchandising reads of the Marketing internal API (P4): products, variants, images, categories, brands, inventory, bundles,
 * banners / sliders, flash-sale offers, coupons. Prices come from the listing columns / PriceResolver (docs/CALCULATIONS.md "Definitions");
 * stock = Σ(on_hand − reserved) over active branches. Store text is plain data (StoreText). No customer data.
 */
class MarketingCatalogService
{
    public function __construct(private MarketingRepositoryInterface $repo, private PriceResolver $prices, private BundleResolver $bundles) {}

    /** Products are not tagged local / international anywhere in the schema. */
    public static function originWarning(): array
    {
        return R::warning('not_tracked', 'Local vs international origin is not recorded on products: `origin` is always null (not inferred).', ['field' => 'origin']);
    }

    private function url(string $route, array $params): ?string
    {
        try {
            return route($route, ['locale' => (string) (setting('default_locale') ?: 'ar')] + $params);
        } catch (\Throwable) {
            return null;
        }
    }

    private function runningFlash(array $productIds): array
    {
        return FlashSaleItem::with('flashSale')->whereIn('product_id', $productIds ?: [0])
            ->whereHas('flashSale', fn ($f) => $f->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now()))
            ->get()->groupBy('product_id')->all();
    }

    // ---------------------------------------------------------------- products

    public function products(MarketingQuery $q): LengthAwarePaginator
    {
        $stock = DB::table('stock_levels as sl')->join('branches as b', 'b.id', '=', 'sl.branch_id')->where('b.is_active', true)->whereNull('b.deleted_at')
            ->selectRaw('sl.product_id, SUM(sl.on_hand - sl.reserved) as available')->groupBy('sl.product_id');
        $qb = Product::query()->with(['brand:id,name', 'categories:id,name', 'images'])
            ->leftJoinSub($stock, 'st', 'st.product_id', '=', 'products.id')->select('products.*', DB::raw('COALESCE(st.available, 0) as stock_available'));

        if ($cat = $q->filter('category')) {
            $c = Category::find($cat);
            $ids = $c ? Category::where('id', $c->id)->orWhere('path', 'like', $c->path.'/%')->pluck('id')->all() : [0];
            $qb->whereHas('categories', fn ($w) => $w->whereIn('categories.id', $ids));
        }
        if ($brand = $q->filter('brand')) {
            $qb->where('products.brand_id', $brand);
        }
        if (array_key_exists('active', $q->filters)) {
            $qb->where('products.is_active', (bool) $q->filters['active']);
        }
        if (array_key_exists('in_stock', $q->filters)) {
            (bool) $q->filters['in_stock'] ? $qb->whereRaw('COALESCE(st.available, 0) > 0') : $qb->whereRaw('COALESCE(st.available, 0) <= 0');
        }
        if (($s = trim((string) $q->filter('search', ''))) !== '') {
            $like = '%'.addcslashes($s, '%_\\').'%';
            $qb->where(fn ($w) => $w->where('products.name_ar', 'like', $like)->orWhere('products.name_en', 'like', $like)->orWhere('products.sku', 'like', $like));
        }
        match ($q->filter('sort', 'newest')) {
            'oldest' => $qb->orderBy('products.id'),
            'name' => $qb->orderBy('products.name_en')->orderBy('products.id'),
            'price_asc' => $qb->orderByRaw('COALESCE(products.sale_price, products.price) IS NULL, COALESCE(products.sale_price, products.price) ASC')->orderBy('products.id'),
            'price_desc' => $qb->orderByRaw('COALESCE(products.sale_price, products.price) DESC')->orderBy('products.id'),
            'stock_asc' => $qb->orderBy('stock_available')->orderBy('products.id'),
            'stock_desc' => $qb->orderByDesc('stock_available')->orderBy('products.id'),
            default => $qb->orderByDesc('products.id'),
        };

        $page = $qb->paginate($q->perPage, ['*'], 'page', $q->page);
        $flash = $this->runningFlash($page->getCollection()->pluck('id')->all());
        $page->setCollection($page->getCollection()->map(fn (Product $p) => $this->productSummary($p, (int) $p->stock_available, $flash[$p->id] ?? collect())));

        return $page;
    }

    private function productSummary(Product $p, int $available, $flashItems): array
    {
        return [
            'id' => $p->id, 'sku' => $p->sku, 'slug' => $p->slug, 'type' => $p->type?->value, 'name' => StoreText::names($p), 'active' => (bool) $p->is_active,
            'brand' => $p->brand ? ['id' => $p->brand->id, 'name' => StoreText::names($p->brand)] : null,
            'categories' => $p->categories->map(fn ($c) => ['id' => $c->id, 'name' => StoreText::names($c)])->values()->all(),
            'pricing' => $this->pricing($p->price, $p->sale_price, $p->cost),
            'discount' => ['on_sale' => $p->sale_price !== null && $p->price !== null && (float) $p->sale_price < (float) $p->price, 'flash_sale' => $flashItems->isNotEmpty()],
            'stock' => ['available' => $available, 'in_stock' => $available > 0],
            'image' => $p->images->first()?->url, 'origin' => null,
            'gift' => ['suitable' => (bool) $p->gift_suitable, 'recipients' => (array) $p->gift_recipients, 'occasions' => (array) $p->gift_occasions],
            'url' => $this->url('storefront.product.show', ['slug' => $p->slug]),
        ];
    }

    private function pricing($price, $sale, $cost): array
    {
        $price = $price !== null ? (string) $price : null;
        $sale = $sale !== null ? (string) $sale : null;
        $eff = $price === null ? null : (($sale !== null && Money::cmp($sale, $price) < 0) ? $sale : $price);
        $hasCost = $cost !== null && (float) $cost > 0;

        return ['price' => $price, 'sale_price' => $sale, 'effective_price' => $eff, 'cost' => $hasCost ? (string) $cost : null, 'cost_missing' => ! $hasCost,
            'margin_percent' => $hasCost && $eff !== null && (float) $eff > 0 ? round(((float) $eff - (float) $cost) / (float) $eff * 100, 2) : null];
    }

    public function product(Product $p): array
    {
        $p->load(['brand', 'categories', 'images', 'tags', 'variants' => fn ($v) => $v->withTrashed()->with(['values', 'images'])]);
        $stock = $this->repo->stockByProduct([$p->id])[$p->id] ?? ['on_hand' => 0, 'reserved' => 0, 'available' => 0, 'stocked' => false];
        $vstock = $this->repo->stockByVariant([$p->id]);
        $flash = $this->runningFlash([$p->id])[$p->id] ?? collect();
        $warnings = [self::originWarning()];

        $variants = $p->variants->map(function (ProductVariant $v) use ($p, $vstock) {
            $src = $v->price !== null ? $v : $p;
            $s = $vstock[$p->id.':'.$v->id] ?? ['on_hand' => 0, 'reserved' => 0, 'available' => 0];

            return ['id' => $v->id, 'sku' => $v->sku, 'barcode' => $v->barcode, 'label' => $v->label(), 'active' => (bool) $v->is_active && ! $v->trashed(),
                'pricing' => $this->pricing($src->price, $src->sale_price, $v->cost ?? $p->cost), 'price_source' => $v->price !== null ? 'variant' : 'product',
                'stock' => ['on_hand' => $s['on_hand'], 'reserved' => $s['reserved'], 'available' => $s['available']],
                'images' => $v->images->map(fn (Image $i) => ['url' => $i->url, 'thumb_url' => $i->thumb_url])->all()];
        })->values()->all();
        if (array_filter($variants, fn ($v) => $v['pricing']['cost_missing']) || ($p->variants->isEmpty() && ($p->cost === null || (float) $p->cost <= 0))) {
            $warnings[] = R::warning('missing_data', 'Purchase cost missing for this product or some variants: margins are null there.', ['field' => 'cost']);
        }

        $seo = [];
        foreach (StoreText::locales() as $l) {
            $title = $p->getTranslation('meta_title', $l, false);
            $desc = $p->getTranslation('meta_description', $l, false);
            $seo[$l] = ['meta_title' => $title !== null ? StoreText::plain($title) : null, 'meta_description' => $desc !== null ? StoreText::plain($desc) : null,
                'missing_meta_title' => $title === null || trim($title) === '', 'missing_meta_description' => $desc === null || trim($desc) === ''];
        }
        $overlay = [];
        foreach ((array) $p->content as $field => $byLocale) {
            if (in_array($field, Product::CONTENT_FIELDS, true) && is_array($byLocale)) {
                $overlay[$field] = array_map(fn ($v) => is_string($v) ? StoreText::plain($v) : null, $byLocale);
            }
        }

        return ['data' => [
            'id' => $p->id, 'sku' => $p->sku, 'barcode' => $p->barcode, 'slug' => $p->slug, 'type' => $p->type?->value, 'active' => (bool) $p->is_active, 'deleted' => $p->trashed(),
            'name' => StoreText::names($p), 'description' => StoreText::rich($p, 'description'), 'storefront_wording' => $overlay ?: null,
            'brand' => $p->brand ? ['id' => $p->brand->id, 'name' => StoreText::names($p->brand), 'slug' => $p->brand->slug] : null,
            'categories' => $p->categories->map(fn ($c) => ['id' => $c->id, 'name' => StoreText::names($c), 'slug' => $c->slug])->values()->all(),
            'tags' => $p->tags->map(fn ($t) => ['id' => $t->id, 'name' => StoreText::names($t)])->values()->all(),
            'pricing' => $this->pricing($p->price, $p->sale_price, $p->cost),
            'discount' => [
                'on_sale' => $p->sale_price !== null && $p->price !== null && (float) $p->sale_price < (float) $p->price,
                'flash_sales' => $flash->map(fn (FlashSaleItem $i) => ['flash_sale_id' => $i->flash_sale_id, 'name' => StoreText::names($i->flashSale), 'variant_id' => $i->product_variant_id ?: null,
                    'discount_type' => $i->discount_type?->value, 'value' => (string) $i->value, 'ends_at' => $i->flashSale->ends_at?->toIso8601String(), 'remaining' => $i->remaining()])->values()->all(),
                'in_bundles' => Bundle::whereHas('items', fn ($w) => $w->where('product_id', $p->id))->get(['id', 'name', 'price', 'is_active'])
                    ->map(fn ($b) => ['id' => $b->id, 'name' => StoreText::names($b), 'price' => (string) $b->price, 'active' => (bool) $b->is_active])->all(),
            ],
            'stock' => $stock + ['min_alert_qty' => $p->min_stock_alert_qty],
            'variants' => $variants,
            'images' => $p->images->map(fn (Image $i) => ['id' => $i->id, 'url' => $i->url, 'thumb_url' => $i->thumb_url, 'sort_order' => $i->sort_order])->values()->all(),
            'seo' => ['slug' => $p->slug, 'locales' => $seo], 'origin' => null,
            'gift' => ['suitable' => (bool) $p->gift_suitable, 'recipients' => (array) $p->gift_recipients, 'occasions' => (array) $p->gift_occasions],
            'urls' => collect(StoreText::locales())->mapWithKeys(fn ($l) => [$l => rescue(fn () => route('storefront.product.show', ['locale' => $l, 'slug' => $p->slug]), null, false)])->all(),
            'created_at' => $p->created_at?->toIso8601String(), 'updated_at' => $p->updated_at?->toIso8601String(),
        ], 'warnings' => $warnings];
    }

    public function productImages(Product $p): array
    {
        $p->load(['images', 'variants.images']);
        $rows = $p->images->map(fn (Image $i) => ['id' => $i->id, 'variant_id' => null, 'url' => $i->url, 'thumb_url' => $i->thumb_url, 'sort_order' => $i->sort_order])->values();
        foreach ($p->variants as $v) {
            $rows = $rows->concat($v->images->map(fn (Image $i) => ['id' => $i->id, 'variant_id' => $v->id, 'url' => $i->url, 'thumb_url' => $i->thumb_url, 'sort_order' => $i->sort_order]));
        }
        $warnings = $rows->isEmpty() ? [R::warning('missing_data', 'This product has no images.', ['field' => 'images'])] : [];

        return ['data' => ['product_id' => $p->id, 'images' => $rows->values()->all(), 'videos' => []], 'warnings' => $warnings];
    }

    // ---------------------------------------------------------------- taxonomy

    public function categories(MarketingQuery $q): LengthAwarePaginator
    {
        $counts = DB::table('category_product as cp')->join('products as p', 'p.id', '=', 'cp.product_id')->whereNull('p.deleted_at')->where('p.is_active', true)
            ->selectRaw('cp.category_id, COUNT(*) c')->groupBy('cp.category_id')->pluck('c', 'category_id');
        $page = Category::query()->orderBy('depth')->orderBy('sort_order')->orderBy('id')->paginate($q->perPage, ['*'], 'page', $q->page);
        $page->setCollection($page->getCollection()->map(fn (Category $c) => [
            'id' => $c->id, 'parent_id' => $c->parent_id, 'depth' => (int) $c->depth, 'path' => $c->path, 'slug' => $c->slug, 'name' => StoreText::names($c),
            'description' => StoreText::names($c, 'description', 500), 'active' => (bool) $c->is_active, 'image' => $c->image_url,
            'active_products' => (int) ($counts[$c->id] ?? 0), 'url' => $this->url('storefront.category.show', ['slug' => $c->slug]),
        ]));

        return $page;
    }

    public function brands(MarketingQuery $q): LengthAwarePaginator
    {
        $counts = Product::where('is_active', true)->whereNotNull('brand_id')->selectRaw('brand_id, COUNT(*) c')->groupBy('brand_id')->pluck('c', 'brand_id');
        $page = Brand::query()->orderBy('sort_order')->orderBy('id')->paginate($q->perPage, ['*'], 'page', $q->page);
        $page->setCollection($page->getCollection()->map(fn (Brand $b) => [
            'id' => $b->id, 'slug' => $b->slug, 'name' => StoreText::names($b), 'active' => (bool) $b->is_active, 'logo' => $b->logo_url,
            'active_products' => (int) ($counts[$b->id] ?? 0), 'url' => $this->url('storefront.brand.show', ['slug' => $b->slug]), 'origin' => null,
        ]));

        return $page;
    }

    // ---------------------------------------------------------------- inventory

    /** Per product (+ variant) stock over active branches with the alert quantity and the valuation at the ledger average cost. */
    public function inventory(MarketingQuery $q): LengthAwarePaginator
    {
        $qb = DB::table('stock_levels as sl')->join('branches as b', 'b.id', '=', 'sl.branch_id')->where('b.is_active', true)->whereNull('b.deleted_at')
            ->join('products as p', 'p.id', '=', 'sl.product_id')->whereNull('p.deleted_at')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'sl.product_variant_id')
            ->selectRaw('sl.product_id, sl.product_variant_id, p.sku as psku, pv.sku as vsku, p.name as pname, p.is_active as active, COALESCE(pv.min_stock_alert_qty, p.min_stock_alert_qty) as alert,
                SUM(sl.on_hand) as on_hand, SUM(sl.reserved) as reserved, ROUND(SUM(sl.on_hand * sl.avg_cost), 2) as value, SUM(CASE WHEN sl.on_hand > 0 AND sl.avg_cost <= 0 THEN sl.on_hand ELSE 0 END) as uncosted_units')
            ->groupBy('sl.product_id', 'sl.product_variant_id', 'p.sku', 'pv.sku', 'p.name', 'p.is_active', 'pv.min_stock_alert_qty', 'p.min_stock_alert_qty')
            ->orderBy('sl.product_id')->orderBy('sl.product_variant_id');
        $page = $qb->paginate($q->perPage, ['*'], 'page', $q->page);
        $default = LowStockService::DEFAULT_ALERT;
        $page->setCollection($page->getCollection()->map(function ($r) use ($default) {
            $available = max(0, (int) $r->on_hand - (int) $r->reserved);
            $alert = $r->alert !== null ? (int) $r->alert : $default;

            return ['product_id' => (int) $r->product_id, 'variant_id' => (int) $r->product_variant_id ?: null, 'sku' => $r->vsku ?? $r->psku, 'name' => StoreText::fromJson($r->pname),
                'active' => (bool) $r->active, 'on_hand' => (int) $r->on_hand, 'reserved' => (int) $r->reserved, 'available' => $available, 'alert_qty' => $alert,
                'low' => $available <= $alert, 'stock_value_at_avg_cost' => (string) $r->value, 'uncosted_units' => (int) $r->uncosted_units];
        }));

        return $page;
    }

    // ---------------------------------------------------------------- merchandising

    public function bundles(MarketingQuery $q): array
    {
        $sold = $this->repo->bundleSales($q->report());
        $page = Bundle::with(['items.product.variants.values'])->orderBy('sort_order')->orderBy('id')->paginate($q->perPage, ['*'], 'page', $q->page);
        $warnings = [];
        $page->setCollection($page->getCollection()->map(function (Bundle $b) use ($sold, &$warnings) {
            $components = $this->bundles->components($b);
            $regular = $components ? $this->bundles->regularTotal($components) : null;
            $cost = '0.00';
            $costKnown = (bool) $components;
            foreach ($components ?? [] as $c) {
                (float) $c['item']['cost'] > 0 ? $cost = Money::add($cost, Money::mul($c['item']['cost'], (string) $c['per'])) : $costKnown = false;
            }
            if ($components && ! $costKnown) {
                $warnings[] = R::warning('missing_data', "Bundle #{$b->id}: some components have no cost, margin not computed.", ['field' => 'cost', 'bundle_id' => $b->id]);
            }
            $perBundle = max(1, (int) $b->items->sum('quantity'));

            return [
                'id' => $b->id, 'slug' => $b->slug, 'name' => StoreText::names($b), 'description' => StoreText::names($b, 'description', 500), 'price' => (string) $b->price,
                'active' => (bool) $b->is_active, 'live' => $b->isLive(), 'starts_at' => $b->starts_at?->toIso8601String(), 'ends_at' => $b->ends_at?->toIso8601String(),
                'purchasable' => $components !== null, 'available' => $components ? $this->bundles->available($b, $components) : 0,
                'regular_total' => $regular, 'savings_percent' => $regular && (float) $regular > 0 ? round(((float) $regular - (float) $b->price) / (float) $regular * 100, 2) : null,
                'components_cost' => $costKnown ? $cost : null, 'margin_percent' => $costKnown && (float) $b->price > 0 ? round(((float) $b->price - (float) $cost) / (float) $b->price * 100, 2) : null,
                'sold_in_range' => intdiv((int) ($sold[$b->id] ?? 0), $perBundle), 'image' => $b->imageUrl(),
                'items' => $b->items->map(fn ($i) => ['product_id' => $i->product_id, 'variant_id' => $i->product_variant_id ?: null, 'quantity' => (int) $i->quantity,
                    'name' => StoreText::names($i->product)])->all(),
                'url' => $this->url('storefront.bundle.show', ['slug' => $b->slug]),
            ];
        }));

        return ['page' => $page, 'warnings' => $warnings];
    }

    private function windowState(bool $active, $start, $end): string
    {
        if (! $active) {
            return 'inactive';
        }
        if ($start && $start->isFuture()) {
            return 'upcoming';
        }

        return $end && $end->isPast() ? 'ended' : 'live';
    }

    private function stateFilter($qb, ?string $state, string $start = 'starts_at', string $end = 'ends_at'): void
    {
        $now = now();
        match ($state) {
            'live', 'active' => $qb->where('is_active', true)->where(fn ($w) => $w->whereNull($start)->orWhere($start, '<=', $now))->where(fn ($w) => $w->whereNull($end)->orWhere($end, '>=', $now)),
            'upcoming' => $qb->where('is_active', true)->where($start, '>', $now),
            'ended' => $qb->where($end, '<', $now),
            'inactive' => $qb->where('is_active', false),
            default => null,
        };
    }

    public function banners(MarketingQuery $q): LengthAwarePaginator
    {
        $qb = Banner::query()->when($q->filter('placement'), fn ($w, $p) => $w->where('placement', $p))->orderBy('placement')->orderBy('sort_order')->orderBy('id');
        $this->stateFilter($qb, $q->filter('state'));
        $page = $qb->paginate($q->perPage, ['*'], 'page', $q->page);
        $page->setCollection($page->getCollection()->map(fn (Banner $b) => [
            'id' => $b->id, 'placement' => $b->placement?->value, 'is_home_slider' => $b->placement?->value === 'home_hero', 'title' => StoreText::names($b, 'title'),
            'images' => collect((array) $b->images)->map(fn ($path) => media_url($path))->all(), 'link' => $b->safeLink(),
            'starts_at' => $b->starts_at?->toIso8601String(), 'ends_at' => $b->ends_at?->toIso8601String(), 'sort_order' => (int) $b->sort_order,
            'active' => (bool) $b->is_active, 'state' => $this->windowState((bool) $b->is_active, $b->starts_at, $b->ends_at),
            'age_days' => $b->created_at ? (int) $b->created_at->diffInDays(now()) : null,
        ]));

        return $page;
    }

    /** Flash sales with every item's regular vs flash price and a negative-margin flag (flash price below the listing cost). */
    public function offers(MarketingQuery $q): array
    {
        $qb = FlashSale::with(['items.product'])->orderByDesc('starts_at')->orderByDesc('id');
        $this->stateFilter($qb, $q->filter('state'));
        $page = $qb->paginate($q->perPage, ['*'], 'page', $q->page);
        $warnings = [];
        $page->setCollection($page->getCollection()->map(function (FlashSale $s) use (&$warnings) {
            $items = $s->items->map(function (FlashSaleItem $i) use ($s, &$warnings) {
                $snap = $i->product ? $this->prices->itemFor($i->product->loadMissing('variants.values'), (int) $i->product_variant_id) : null;
                $regular = $snap['price'] ?? null;
                $flash = $regular !== null ? $this->prices->flashPrice($i, $regular) : null;
                $cost = $snap && (float) $snap['cost'] > 0 ? $snap['cost'] : null;
                if ($snap && $cost === null) {
                    $warnings[] = R::warning('missing_data', "Flash sale #{$s->id}: product #{$i->product_id} has no cost, margin unknown.", ['field' => 'cost', 'flash_sale_id' => $s->id, 'product_id' => $i->product_id]);
                }

                return ['product_id' => $i->product_id, 'variant_id' => $i->product_variant_id ?: null, 'name' => StoreText::names($i->product),
                    'discount_type' => $i->discount_type?->value, 'value' => (string) $i->value, 'qty_limit' => $i->qty_limit, 'sold' => (int) $i->sold, 'remaining' => $i->remaining(),
                    'regular_price' => $regular, 'flash_price' => $flash, 'cost' => $cost,
                    'flash_margin_percent' => $cost !== null && $flash !== null && (float) $flash > 0 ? round(((float) $flash - (float) $cost) / (float) $flash * 100, 2) : null,
                    'negative_margin' => $cost !== null && $flash !== null ? Money::cmp($flash, $cost) < 0 : null, 'purchasable' => $snap !== null];
            })->values()->all();

            return ['id' => $s->id, 'name' => StoreText::names($s), 'starts_at' => $s->starts_at?->toIso8601String(), 'ends_at' => $s->ends_at?->toIso8601String(),
                'active' => (bool) $s->is_active, 'state' => $this->windowState((bool) $s->is_active, $s->starts_at, $s->ends_at), 'items' => $items];
        }));

        return ['page' => $page, 'warnings' => $warnings];
    }

    /** Discount codes with rules and usage (in the range and lifetime). Never any customer data. */
    public function coupons(MarketingQuery $q): LengthAwarePaginator
    {
        $usage = $this->repo->couponUsage($q->report());
        $qb = DiscountCode::query()->orderByDesc('id');
        $this->stateFilter($qb, $q->filter('state'));
        $page = $qb->paginate($q->perPage, ['*'], 'page', $q->page);
        $page->setCollection($page->getCollection()->map(fn (DiscountCode $d) => [
            'id' => $d->id, 'code' => $d->code, 'type' => $d->type?->value, 'value' => (string) $d->value, 'min_order' => $d->min_order !== null ? (string) $d->min_order : null,
            'max_discount' => $d->max_discount !== null ? (string) $d->max_discount : null, 'free_shipping' => (bool) $d->free_shipping,
            'scope' => $d->scope?->value, 'product_ids' => array_map('intval', (array) $d->product_ids), 'category_ids' => array_map('intval', (array) $d->category_ids),
            'usage_limit' => $d->usage_limit, 'per_customer_limit' => $d->per_customer_limit, 'used_count' => (int) $d->used_count,
            'remaining_uses' => $d->usage_limit !== null ? max(0, $d->usage_limit - (int) $d->used_count) : null,
            'starts_at' => $d->starts_at?->toIso8601String(), 'ends_at' => $d->ends_at?->toIso8601String(), 'active' => (bool) $d->is_active,
            'state' => $d->usage_limit !== null && $d->used_count >= $d->usage_limit ? 'exhausted' : $this->windowState((bool) $d->is_active, $d->starts_at, $d->ends_at),
            'usage_in_range' => $usage[$d->id] ?? ['usages' => 0, 'discount' => '0.00', 'orders_total' => '0.00'],
        ]));

        return $page;
    }
}
