<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CustomPage;
use App\Services\Storefront\CustomPageProducts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** `/{slug}` (no locale prefix): locale = ?lang=xx or the default locale. Registered last in storefront.php. */
class CustomPageController extends Controller
{
    public function show(Request $request, string $slug, CustomPageProducts $products)
    {
        $page = CustomPage::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $locale = in_array($request->query('lang'), (array) setting('locales', []), true) ? $request->query('lang') : setting('default_locale');
        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        view()->share('isRtl', locale_is_rtl($locale));

        return view('storefront.custom-page', [
            'page' => $page,
            'items' => $products->paginate($page, $request->only(['price_min', 'price_max', 'sort'])),
            'sorts' => ['newest', 'price_asc', 'price_desc', 'best_selling'],
        ]);
    }
}
