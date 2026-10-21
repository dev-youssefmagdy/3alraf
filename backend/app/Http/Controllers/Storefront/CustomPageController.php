<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CustomPage;
use App\Models\Language;
use App\Services\Storefront\CustomPageProducts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** `tenant-domain/{slug}` (no locale prefix): locale = ?lang=xx or the tenant default. Registered last in storefront.php. */
class CustomPageController extends Controller
{
    public function show(Request $request, string $slug, CustomPageProducts $products)
    {
        $page = CustomPage::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $tenant = current_tenant();
        $locale = in_array($request->query('lang'), $tenant->locales, true) ? $request->query('lang') : $tenant->default_locale;
        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        view()->share('isRtl', (bool) Language::activeList()->firstWhere('code', $locale)?->isRtl());

        return view('storefront.custom-page', [
            'page' => $page,
            'items' => $products->paginate($page, $request->only(['price_min', 'price_max', 'sort'])),
            'sorts' => ['newest', 'price_asc', 'price_desc', 'best_selling'],
        ]);
    }
}
