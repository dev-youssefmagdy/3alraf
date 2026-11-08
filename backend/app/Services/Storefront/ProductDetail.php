<?php

namespace App\Services\Storefront;

use App\Enums\ProductListType;
use App\Enums\ReviewStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Review;
use App\Services\Lists\ProductListResolver;
use App\Services\ReviewService;
use App\Services\WishlistService;

/** Everything a product page (web Blade or mobile API) shows, assembled once. */
class ProductDetail
{
    public function __construct(private ReviewService $reviews, private WishlistService $wishlist, private ProductListResolver $lists, private ProductPageData $page, private CardPresenter $cards, private ProductGallery $gallery) {}

    /** @return array{product:Product,media:\Illuminate\Support\Collection,summary:array,reviews:\Illuminate\Support\Collection,canReview:bool,myReview:?Review,inWishlist:bool,related:\Illuminate\Support\Collection,picker:array,crumbs:\Illuminate\Support\Collection} */
    public function load(string $slug, ?Customer $customer): array
    {
        $product = Product::with(['images', 'videos', 'variants.values', 'variants.images', 'brand', 'categories'])->where('slug', $slug)->where('is_active', true)->whereNotNull('price')->firstOrFail();
        $product->applyContent();   // storefront wording overlay (products.content); display only, never saved

        return [
            'product' => $product, 'media' => $this->gallery->items($product), 'summary' => $this->reviews->summary($product),
            'reviews' => Review::with('customer')->where('product_id', $product->id)->where('status', ReviewStatus::APPROVED->value)->latest('id')->limit(30)->get(),
            'canReview' => $customer && $this->reviews->purchaseOrder($customer, $product) !== null,
            'myReview' => $customer ? Review::where('product_id', $product->id)->where('customer_id', $customer->id)->first() : null,
            'inWishlist' => $customer && $this->wishlist->has($customer, $product->id),
            'related' => $this->cards->decorate($this->lists->resolve(ProductListType::RECOMMENDED, 8, $product->id)),
            'picker' => $this->page->build($product), 'crumbs' => $product->categories->sortByDesc('depth')->first()?->breadcrumbs() ?? collect(),
        ];
    }
}
