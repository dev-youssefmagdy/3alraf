<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\ProductListType;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\TenantProduct;
use App\Services\Lists\ProductListResolver;
use App\Services\ReviewService;
use App\Services\Storefront\CardPresenter;
use App\Services\Storefront\ProductPageData;
use App\Services\WishlistService;

/** Minimal product page: details, price, approved reviews, wishlist toggle, related (recommended) rail. Affiliate clicks are tracked by the `affiliate.track` middleware. */
class ProductController extends Controller
{
    public function show(string $locale, string $slug, ReviewService $reviews, WishlistService $wishlist, ProductListResolver $lists, ProductPageData $page, CardPresenter $cards)
    {
        $product = Product::with(['images', 'variants.values', 'variants.images', 'brand', 'categories'])->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $tp = TenantProduct::where('product_id', $product->id)->where('is_active', true)->firstOrFail();
        $customer = auth('web')->user();

        return view('storefront.product', [
            'product' => $product, 'tp' => $tp, 'summary' => $reviews->summary($product),
            'reviews' => Review::with('customer')->where('product_id', $product->id)->where('status', ReviewStatus::APPROVED->value)->latest('id')->limit(30)->get(),
            'canReview' => $customer && $reviews->purchaseOrder($customer, $product) !== null,
            'myReview' => $customer ? Review::where('product_id', $product->id)->where('customer_id', $customer->id)->first() : null,
            'inWishlist' => $customer && $wishlist->has($customer, $product->id),
            'related' => $cards->decorate($lists->resolve(ProductListType::RECOMMENDED, 8, $product->id)),
            'picker' => $page->build($product, $tp), 'crumbs' => $product->categories->sortByDesc('depth')->first()?->breadcrumbs() ?? collect(),
        ]);
    }
}
