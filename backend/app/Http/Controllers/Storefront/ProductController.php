<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\ProductDetail;

/** Minimal product page: details, price, approved reviews, wishlist toggle, related (recommended) rail. Affiliate clicks are tracked by the `affiliate.track` middleware. */
class ProductController extends Controller
{
    public function show(string $locale, string $slug, ProductDetail $detail)
    {
        return view('storefront.product', $detail->load($slug, auth('web')->user()));
    }
}
