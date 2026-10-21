<?php

namespace App\Services\Storefront;

use App\Enums\BannerPlacement;
use App\Enums\ProductListType;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\PaymentMethod;
use App\Models\StaticPage;
use App\Models\TenantProduct;
use App\Services\Lists\ProductListResolver;
use Illuminate\Support\Facades\Cache;

/** Data for the shared layout (mega menu, footer) and the home page sections. */
class StorefrontData
{
    public function __construct(private ProductListResolver $lists, private CardPresenter $cards, private CatalogSearch $search) {}

    /** Active category tree as nested arrays (cached per tenant + locale for a minute). */
    public function menu(): array
    {
        return Cache::remember('sf.menu.'.current_tenant()?->id.'.'.app()->getLocale(), 60, function () {
            $all = Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->groupBy('parent_id');
            $build = function (?int $parent) use (&$build, $all): array {
                return ($all[$parent] ?? collect())->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'image' => $c->image_url, 'icon' => $c->icon, 'children' => $build($c->id),
                ])->values()->all();
            };

            return $build(null);
        });
    }

    public function footer(): array
    {
        return [
            'pages' => StaticPage::where('is_active', true)->orderBy('id')->get(['slug', 'title']),
            'payments' => PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(),
        ];
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
            'brands' => Brand::query()->where('is_active', true)->whereHas('products', fn ($p) => $p->where('is_active', true)->whereIn('id', TenantProduct::where('is_active', true)->select('product_id')))
                ->orderBy('sort_order')->limit(12)->get(),
        ];
    }

    /** Running flash sale (earliest ending) with its sellable products. */
    private function flash(): ?array
    {
        $sale = FlashSale::query()->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now())->orderBy('ends_at')->first();
        if (! $sale) {
            return null;
        }
        $ids = $sale->items()->pluck('product_id')->unique()->all();
        $items = TenantProduct::query()->where('is_active', true)->whereIn('product_id', $ids)->whereHas('product', fn ($p) => $p->where('is_active', true))->with('product.images')->limit(12)->get();

        return $items->isEmpty() ? null : ['sale' => $sale, 'items' => $this->cards->decorate($items)];
    }

    /** Up to 4 root categories that have sellable products, 8 each. */
    private function categoryRails(): array
    {
        $out = [];
        foreach (Category::query()->where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->orderBy('id')->get() as $cat) {
            $items = $this->search->paginate($this->search->scope($cat), new \App\DTOs\Storefront\CatalogFilter(perPage: 8))->getCollection();
            if ($items->isNotEmpty()) {
                $out[] = ['category' => $cat, 'items' => $items];
            }
            if (count($out) >= 4) {
                break;
            }
        }

        return $out;
    }
}
