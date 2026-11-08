<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\GiftFinderCartRequest;
use App\Http\Requests\Storefront\GiftFinderRequest;
use App\Models\Category;
use App\Services\GiftFinder\GiftFinderService;
use Illuminate\Http\JsonResponse;

class GiftFinderController extends Controller
{
    public function __construct(private GiftFinderService $svc) {}

    public function show()
    {
        return view('storefront.gift-finder', $this->formData() + ['initial' => null]);
    }

    /** Share link of a stored search: same page, prefilled answers, the stored suggestions rendered on load. */
    public function shared(string $locale, string $token)
    {
        return view('storefront.gift-finder', $this->formData() + ['initial' => $this->svc->shared($token)]);
    }

    public function search(GiftFinderRequest $r): JsonResponse
    {
        return $this->ok(null, null, $this->svc->search($r->validated()));
    }

    public function cart(GiftFinderCartRequest $r, string $locale, string $token): JsonResponse
    {
        $res = $this->svc->prepareCart($token, (array) $r->input('product_ids', []), (array) $r->input('variants', []), (array) $r->input('bundle_ids', []));
        $any = $res['added'] || $res['bundles_added'];

        return $any ? $this->ok(__('orders.added_to_cart'), route('storefront.cart.show', ['locale' => $locale]), $res)
            : response()->json(['success' => false, 'message' => __('orders.item_unavailable'), 'data' => $res], 422);
    }

    private function formData(): array
    {
        return ['occasions' => GiftFinderService::OCCASIONS, 'recipients' => GiftFinderService::RECIPIENTS,
            'categories' => Category::where('is_active', true)->orderBy('path')->get(['id', 'name', 'depth'])];
    }
}
