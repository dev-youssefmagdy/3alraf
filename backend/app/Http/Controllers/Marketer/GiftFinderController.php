<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\GiftFinderSession;
use App\Models\Product;
use App\Services\GiftFinder\GiftFinderInsights;
use App\Services\GiftFinder\GiftFinderService;
use App\Services\GiftFinder\GiftFinderSettings;
use App\Services\GiftFinder\GiftTagSuggester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin Gift Finder: stats, search history and the per-product gift tags (perms gift_finder.view / gift_finder.manage on the routes). Marketer panel (P4); old /admin URLs 301 here. */
class GiftFinderController extends Controller
{
    public function index(GiftFinderService $svc, GiftFinderSettings $settings, GiftFinderInsights $insights)
    {
        $sessions = GiftFinderSession::latest('id')->paginate(15);
        $names = Product::whereIn('id', $sessions->getCollection()->flatMap(fn ($s) => (array) $s->result_ids)->unique())->get()->keyBy('id');
        $bundleNames = Bundle::withTrashed()->whereIn('id', $sessions->getCollection()->flatMap(fn ($s) => (array) $s->bundle_ids)->unique())->get()->keyBy('id');

        return view('marketer.gift-finder.index', [
            'enabled' => $settings->enabled(), 'stats' => $svc->stats(30), 'url' => route('storefront.gift-finder.show', ['locale' => setting('default_locale')]),
            'tagged' => Product::where('gift_suitable', true)->count(), 'total' => Product::where('is_active', true)->count(),
            'sessions' => $sessions, 'cats' => Category::whereIn('id', $sessions->getCollection()->pluck('category_id')->filter())->get()->keyBy('id'),
            // session id => the suggested products, flagged with the ones the shopper added to the cart (read by the "view results" modal)
            'details' => $sessions->getCollection()->mapWithKeys(fn ($s) => [$s->id => collect((array) $s->result_ids)->map(fn ($id) => $names->get($id))->filter()
                ->map(fn ($p) => ['name' => (string) $p->name, 'sku' => $p->sku, 'in_cart' => in_array($p->id, (array) $s->cart_ids, true)])->values()
                ->concat(collect((array) $s->bundle_ids)->map(fn ($id) => $bundleNames->get($id))->filter()
                    ->map(fn ($b) => ['name' => '['.__('gift_finder.bundle').'] '.$b->name, 'sku' => null, 'in_cart' => in_array($b->id, (array) $s->cart_bundle_ids, true)]))->all()])->all(),
            // P3: live bundles none of whose items carries a recipient / occasion tag (they get no tag fit points)
            'untagged' => $insights->untagged(20),
        ]);
    }

    public function tags()
    {
        return view('marketer.gift-finder.tags', [
            'products' => Product::where('is_active', true)->get()->sortBy(fn ($p) => $p->name)->values(),
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
        Product::whereIn('id', array_keys($data['tags'] ?? []))->get()->each(function (Product $tp) use ($data) {
            $t = $data['tags'][$tp->id];
            $tp->update(['gift_suitable' => (bool) ($t['suitable'] ?? false), 'gift_recipients' => array_values($t['recipients'] ?? []), 'gift_occasions' => array_values($t['occasions'] ?? [])]);
        });

        return redirect()->route('marketer.gift-finder.tags')->with('success', __('gift_finder.tags_saved'));
    }

    public function suggestTags(GiftTagSuggester $suggester): RedirectResponse
    {
        $n = $suggester->applyToUntagged();

        return redirect()->route('marketer.gift-finder.tags')->with('success', __('gift_finder.tags_suggested', ['n' => $n]));
    }
}
