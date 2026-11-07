<?php

namespace App\Services\Storefront;

use App\Enums\BannerPlacement;
use App\Enums\ProductListType;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StaticPage;
use App\Services\Lists\ProductListResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Data for the shared layout (mega menu, footer) and the home page sections. */
class StorefrontData
{
    public function __construct(private ProductListResolver $lists, private CardPresenter $cards, private CatalogSearch $search) {}

    /** Active category tree as nested arrays (cached per locale for a minute). */
    public function menu(): array
    {
        return Cache::remember('sf.menu.'.app()->getLocale(), 60, function () {
            $all = Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->groupBy('parent_id');
            $build = function (?int $parent) use (&$build, $all): array {
                return ($all[$parent] ?? collect())->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'image' => $c->image_url, 'icon' => $c->icon, 'children' => $build($c->id),
                ])->values()->all();
            };

            return $build(null);
        });
    }

    /** Footer data as plain arrays (cached per locale for a minute): static pages, ACTIVE payment methods (+icon kind), app store links. */
    public function footer(): array
    {
        return Cache::remember('sf.footer.'.app()->getLocale(), 60, fn () => [
            'pages' => StaticPage::where('is_active', true)->orderBy('id')->get(['slug', 'title'])->map(fn ($p) => ['slug' => $p->slug, 'title' => $p->title])->all(),
            'blog' => \App\Models\BlogPost::published()->exists(),
            'payments' => PaymentMethod::available()->orderBy('sort_order')->get()->map(fn ($m) => ['name' => $m->name, 'type' => $m->type->value, 'provider' => $m->provider?->value])->all(),
            'apps' => ['ios' => setting('app_links.ios'), 'android' => setting('app_links.android')],
        ]);
    }

    public function home(): array
    {
        $banners = Banner::query()->live()->orderBy('sort_order')->orderBy('id')->get()->groupBy(fn ($b) => $b->placement->value);
        $dec = fn ($c) => $this->cards->decorate($c);

        return [
            'hero' => $banners[BannerPlacement::HOME_HERO->value] ?? collect(),
            'strips' => $banners[BannerPlacement::HOME_STRIP->value] ?? collect(),
            'tiles' => $banners[BannerPlacement::CATEGORY_TILE->value] ?? collect(),
            'popup' => ($banners[BannerPlacement::POPUP->value] ?? collect())->first(),
            'categories' => $this->menu(),
            'flash' => $this->flash(),
            'rails' => [
                'recommended' => $dec($this->lists->resolve(ProductListType::RECOMMENDED)),
                'most_sold' => $dec($this->lists->resolve(ProductListType::MOST_SOLD)),
                'most_reviewed' => $dec($this->lists->resolve(ProductListType::MOST_REVIEWED)),
            ],
            'categoryRails' => $this->categoryRails(),
            'brands' => $this->brands(),
        ];
    }

    /** Active brands with sellable products, newest-first by sort order, with a product count for the home strip. */
    private function brands()
    {
        $sellable = fn ($p) => $p->where('is_active', true)->whereNotNull('price');

        return Brand::query()->where('is_active', true)->whereHas('products', $sellable)
            ->withCount(['products as sellable_count' => $sellable])
            ->orderBy('sort_order')->orderBy('name')->limit(24)->get();
    }

    /** Running flash sale (earliest ending) with its sellable products. */
    private function flash(): ?array
    {
        $sale = FlashSale::query()->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now())->orderBy('ends_at')->first();
        if (! $sale) {
            return null;
        }
        $ids = $sale->items()->pluck('product_id')->unique()->all();
        $items = Product::query()->where('is_active', true)->whereNotNull('price')->whereIn('id', $ids)->with('images')->limit(12)->get();

        return $items->isEmpty() ? null : ['sale' => $sale, 'items' => $this->cards->decorate($items)];
    }

    /**
     * Up to 4 root categories that have sellable products, 8 newest each. Constant number of queries: one for the category tree,
     * one for (product, category) pairs of sellable products, one to load the chosen cards.
     */
    private function categoryRails(): array
    {
        $cats = Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $roots = $cats->whereNull('parent_id')->values();
        if ($roots->isEmpty()) {
            return [];
        }
        $rootOf = $cats->mapWithKeys(fn ($c) => [$c->id => (int) explode('/', $c->path)[0]]);
        $sellable = Product::query()->where('is_active', true)->whereNotNull('price')->pluck('id');
        $byRoot = [];
        foreach (DB::table('category_product')->whereIn('product_id', $sellable)->get(['product_id', 'category_id']) as $row) {
            if ($root = $rootOf[$row->category_id] ?? null) {
                $byRoot[$root][$row->product_id] = (int) $row->product_id;
            }
        }
        $picked = [];
        foreach ($roots as $root) {
            if (! empty($byRoot[$root->id])) {
                $ids = collect($byRoot[$root->id])->sortDesc()->take(8)->values()->all();
                $picked[$root->id] = $ids;
            }
            if (count($picked) >= 4) {
                break;
            }
        }
        $cards = $this->cards->decorate(Product::query()->whereIn('id', collect($picked)->flatten())->with('images')->get())->keyBy('id');

        return $roots->filter(fn ($r) => isset($picked[$r->id]))->map(fn ($r) => [
            'category' => $r, 'items' => collect($picked[$r->id])->map(fn ($id) => $cards->get($id))->filter()->values(),
        ])->values()->all();
    }
}
