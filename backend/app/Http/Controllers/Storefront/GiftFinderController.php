<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\GiftFinderRequest;
use App\Models\Category;
use App\Services\Growth\GiftFinderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftFinderController extends Controller
{
    public function __construct(private GiftFinderService $svc) {}

    public function show()
    {
        return view('storefront.gift-finder', ['occasions' => GiftFinderService::OCCASIONS, 'recipients' => GiftFinderService::RECIPIENTS, 'categories' => Category::where('is_active', true)->orderBy('id')->get(['id', 'name'])]);
    }

    public function search(GiftFinderRequest $r): JsonResponse
    {
        return $this->ok(null, null, $this->svc->search($r->validated()));
    }

    public function cart(Request $r, string $locale, string $token): JsonResponse
    {
        $res = $this->svc->prepareCart($token, (array) $r->input('product_ids', []));

        return $this->ok(__('orders.added_to_cart'), route('storefront.cart.show', ['locale' => $locale]), $res);
    }
}
