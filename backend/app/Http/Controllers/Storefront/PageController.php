<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;

/** Static pages: /{locale}/page/{slug} (privacy-policy, terms, about ...), translatable. */
class PageController extends Controller
{
    public function show(string $locale, string $slug)
    {
        return view('storefront.page', ['page' => StaticPage::where('slug', $slug)->where('is_active', true)->firstOrFail()]);
    }
}
