<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomPage;
use App\Models\StaticPage;
use App\Models\TenantProduct;

/** `/sitemap.xml` for the current tenant (every locale). */
class SitemapController extends Controller
{
    public function __invoke()
    {
        $locales = current_tenant()->locales;
        $urls = [];
        $add = function (string $route, array $params, ?string $mod = null) use ($locales, &$urls) {
            foreach ($locales as $l) {
                $urls[] = ['loc' => route($route, ['locale' => $l] + $params), 'mod' => $mod];
            }
        };
        $add('storefront.home', []);
        foreach (Category::where('is_active', true)->get(['slug', 'updated_at']) as $c) {
            $add('storefront.category.show', ['slug' => $c->slug], $c->updated_at?->toDateString());
        }
        foreach (\App\Models\Brand::where('is_active', true)->get(['slug', 'updated_at']) as $b) {
            $add('storefront.brand.show', ['slug' => $b->slug], $b->updated_at?->toDateString());
        }
        $tps = TenantProduct::where('is_active', true)->with('product:id,slug,is_active,updated_at')->get();
        foreach ($tps as $tp) {
            if ($tp->product?->is_active) {
                $add('storefront.product.show', ['slug' => $tp->product->slug], $tp->product->updated_at?->toDateString());
            }
        }
        foreach (StaticPage::where('is_active', true)->get(['slug']) as $p) {
            $add('storefront.pages.show', ['slug' => $p->slug]);
        }
        foreach (\App\Models\BlogPost::where('is_active', true)->get(['slug']) as $p) {
            $add('storefront.blog.show', ['slug' => $p->slug]);
        }
        foreach (CustomPage::where('is_active', true)->get(['slug']) as $p) {
            $urls[] = ['loc' => url('/'.$p->slug), 'mod' => null];
        }

        return response()->view('storefront.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }
}
