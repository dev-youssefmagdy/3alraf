<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\TenantProduct;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;

class WishlistController extends Controller
{
    public function __construct(private WishlistService $service) {}

    public function index()
    {
        return view('storefront.wishlist', ['items' => $this->service->products(auth('web')->user())]);
    }

    public function toggle(string $locale, int $product): JsonResponse
    {
        abort_unless(TenantProduct::where('product_id', $product)->where('is_active', true)->exists(), 404);
        $in = $this->service->toggle(auth('web')->user(), $product);

        return $this->ok($in ? __('growth.wishlist_added') : __('growth.wishlist_removed'), null, ['in_wishlist' => $in]);
    }
}
