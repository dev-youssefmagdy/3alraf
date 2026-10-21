<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Storefront\AddressRequest;
use App\Http\Requests\Storefront\ReviewRequest;
use App\Http\Resources\Api\AddressResource;
use App\Http\Resources\Api\ProductCardResource;
use App\Http\Resources\Api\ReviewResource;
use App\DTOs\ReviewData;
use App\Models\Product;
use App\Models\Review;
use App\Models\TenantProduct;
use App\Services\CustomerAddressService;
use App\Services\ReviewService;
use App\Services\Storefront\CardPresenter;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Customer data: addresses, wishlist, reviews. */
class AccountController extends ApiController
{
    public function addresses(Request $r, CustomerAddressService $service): JsonResponse
    {
        return $this->list(AddressResource::collection($service->forCustomer($r->user()->id)));
    }

    public function storeAddress(AddressRequest $r, CustomerAddressService $service): JsonResponse
    {
        return $this->respond(new AddressResource($service->save($r->user(), $r->validated())->load('city')), __('admin.saved'), status: 201);
    }

    public function updateAddress(AddressRequest $r, CustomerAddressService $service, int $id): JsonResponse
    {
        return $this->respond(new AddressResource($service->save($r->user(), $r->validated(), $id)->load('city')), __('admin.saved'));
    }

    public function destroyAddress(Request $r, CustomerAddressService $service, int $id): JsonResponse
    {
        $service->delete($r->user(), $id);

        return $this->respond(null, __('admin.deleted'));
    }

    public function wishlist(Request $r, WishlistService $service, CardPresenter $cards): JsonResponse
    {
        return $this->list(ProductCardResource::collection($cards->decorate($service->products($r->user()))));
    }

    public function toggleWishlist(Request $r, WishlistService $service, int $product): JsonResponse
    {
        abort_unless(TenantProduct::where('product_id', $product)->where('is_active', true)->exists(), 404);
        $in = $service->toggle($r->user(), $product);

        return $this->respond(['in_wishlist' => $in], $in ? __('growth.wishlist_added') : __('growth.wishlist_removed'));
    }

    public function reviews(string $slug): JsonResponse
    {
        $p = Product::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return $this->page(Review::with('customer')->where('product_id', $p->id)->where('status', \App\Enums\ReviewStatus::APPROVED->value)->latest('id')->paginate(20), ReviewResource::class);
    }

    /** Only for delivered purchases; one review per product (edits go back to pending moderation). */
    public function storeReview(ReviewRequest $r, ReviewService $service, string $slug): JsonResponse
    {
        $review = $service->submit($r->user(), Product::where('slug', $slug)->where('is_active', true)->firstOrFail(), ReviewData::fromRequest($r));

        return $this->respond(new ReviewResource($review), __('growth.review_submitted'), status: 201);
    }
}
