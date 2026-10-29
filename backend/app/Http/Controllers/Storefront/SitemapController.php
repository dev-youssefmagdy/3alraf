<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\CustomPage;
use App\Models\StaticPage;
use App\Models\TenantProduct;

/** `/sitemap.xml` for the current tenant: every locale, hreflang alternates (xhtml:link) and product images. */
class SitemapController extends Controller
{
    public function __invoke()
    {
        $tenant = current_tenant();
        $locales = $tenant->locales;
        $urls = [];
        $add = function (string $route, array $params, ?string $mod = null, array $images = []) use ($locales, $tenant, &$urls) {
            $alts = [];
            foreach ($locales as $l) {
                $alts[$l] = route($route, ['locale' => $l] + $params);
            }
            $alts['x-default'] = route($route, ['locale' => $tenant->default_locale] + $params);
            foreach ($locales as $l) {
                $urls[] = ['loc' => $alts[$l], 'mod' => $mod, 'alts' => $alts, 'images' => $images];
            }
        };
        $add('storefront.home', []);
        $add('storefront.brands.index', []);
        if (Bundle::where('is_active', true)->exists()) {
            $add('storefront.bundles.index', []);
        }
        foreach (Category::where('is_active', true)->get(['slug', 'updated_at']) as $c) {
            $add('storefront.category.show', ['slug' => $c->slug], $c->updated_at?->toDateString());
        }
        foreach (Brand::where('is_active', true)->get(['slug', 'updated_at']) as $b) {
            $add('storefront.brand.show', ['slug' => $b->slug], $b->updated_at?->toDateString());
        }
        $tps = TenantProduct::where('is_active', true)->with(['product:id,slug,name,is_active,updated_at', 'product.images'])->get();
        foreach ($tps as $tp) {
            if ($tp->product?->is_active) {
                $imgs = $tp->product->images->take(5)->map(fn ($i) => ['loc' => $i->url, 'title' => $tp->product->name])->all();
                $add('storefront.product.show', ['slug' => $tp->product->slug], $tp->product->updated_at?->toDateString(), $imgs);
            }
        }
        foreach (Bundle::where('is_active', true)->get(['slug', 'updated_at']) as $b) {
            $add('storefront.bundle.show', ['slug' => $b->slug], $b->updated_at?->toDateString());
        }
        foreach (StaticPage::where('is_active', true)->get(['slug', 'updated_at']) as $p) {
            $add('storefront.pages.show', ['slug' => $p->slug], $p->updated_at?->toDateString());
        }
        $posts = BlogPost::published()->get(['slug', 'updated_at']);
        if ($posts->isNotEmpty()) {
            $add('storefront.blog.index', []);
        }
        foreach ($posts as $p) {
            $add('storefront.blog.show', ['slug' => $p->slug], $p->updated_at?->toDateString());
        }
        foreach (CustomPage::where('is_active', true)->get(['slug', 'updated_at']) as $p) {
            $urls[] = ['loc' => url('/'.$p->slug), 'mod' => $p->updated_at?->toDateString(), 'alts' => [], 'images' => []];
        }

        return response()->view('storefront.sitemap', ['urls' => array_slice($urls, 0, 50000)])->header('Content-Type', 'application/xml; charset=UTF-8')->header('Cache-Control', 'public, max-age=3600');
    }
}
