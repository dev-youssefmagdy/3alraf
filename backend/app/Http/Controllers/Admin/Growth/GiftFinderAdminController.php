<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GiftFinderSession;
use App\Models\Product;
use App\Models\TenantProduct;
use App\Services\Growth\GiftFinder\GiftTagSuggester;
use App\Services\Growth\GiftFinderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GiftFinderAdminController extends Controller
{
    public function index(GiftFinderService $svc)
    {
        $sessions = GiftFinderSession::latest('id')->paginate(15);
        $names = Product::whereIn('id', $sessions->getCollection()->flatMap(fn ($s) => (array) $s->result_ids)->unique())->get()->keyBy('id');

        return view('tenant.growth.gift-finder', [
            'stats' => $svc->stats(30), 'url' => route('storefront.gift-finder.show', ['locale' => current_tenant()->default_locale]),
            'tagged' => TenantProduct::where('gift_suitable', true)->count(), 'total' => TenantProduct::where('is_active', true)->count(),
            'sessions' => $sessions, 'cats' => Category::whereIn('id', $sessions->getCollection()->pluck('category_id')->filter())->get()->keyBy('id'),
            // session id => the suggested products, flagged with the ones the shopper added to the cart (read by the "view results" modal)
            'details' => $sessions->getCollection()->mapWithKeys(fn ($s) => [$s->id => collect((array) $s->result_ids)->map(fn ($id) => $names->get($id))->filter()
                ->map(fn ($p) => ['name' => (string) $p->name, 'sku' => $p->sku, 'in_cart' => in_array($p->id, (array) $s->cart_ids, true)])->values()->all()])->all(),
        ]);
    }

    public function tags()
    {
        return view('tenant.growth.gift-finder-tags', [
            'products' => TenantProduct::with('product')->where('is_active', true)->get()->sortBy(fn ($tp) => $tp->product?->name)->values(),
            'occasions' => GiftFinderService::OCCASIONS, 'recipients' => GiftFinderService::RECIPIENTS,
        ]);
    }

    public function saveTags(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'tags' => ['array'],
            'tags.*.suitable' => ['nullable', 'boolean'],
            'tags.*.recipients' => ['nullable', 'array'], 'tags.*.recipients.*' => [Rule::in(GiftFinderService::RECIPIENTS)],
            'tags.*.occasions' => ['nullable', 'array'], 'tags.*.occasions.*' => [Rule::in(GiftFinderService::OCCASIONS)],
        ]);
        // tenant-scoped lookup: ids from other stores simply do not match
        TenantProduct::whereIn('id', array_keys($data['tags'] ?? []))->get()->each(function (TenantProduct $tp) use ($data) {
            $t = $data['tags'][$tp->id];
            $tp->update(['gift_suitable' => (bool) ($t['suitable'] ?? false), 'gift_recipients' => array_values($t['recipients'] ?? []), 'gift_occasions' => array_values($t['occasions'] ?? [])]);
        });

        return redirect()->route('tenant.growth.gift-finder.tags')->with('success', __('growth_hub.gift.tags_saved'));
    }

    public function suggestTags(GiftTagSuggester $suggester): RedirectResponse
    {
        $n = $suggester->applyToUntagged();

        return redirect()->route('tenant.growth.gift-finder.tags')->with('success', __('growth_hub.gift.tags_suggested', ['n' => $n]));
    }
}
