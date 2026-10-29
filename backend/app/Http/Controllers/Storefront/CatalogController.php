<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Storefront\CatalogFilter;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Tag;
use App\Models\TenantProduct;
use App\Services\Storefront\CatalogSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Category listing and search results (same view; AJAX requests get the grid fragment as JSON). */
class CatalogController extends Controller
{
    public function __construct(private CatalogSearch $search) {}

    public function category(Request $request, string $locale, string $slug)
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return $this->render($request, $category, null);
    }

    /** All brands that have at least one sellable product. */
    public function brands()
    {
        $sellable = fn ($p) => $p->where('is_active', true)->whereIn('id', TenantProduct::where('is_active', true)->select('product_id'));
        $brands = Brand::query()->where('is_active', true)
            ->withCount(['products as sellable_count' => $sellable])
            ->whereHas('products', $sellable)
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('storefront.brands', compact('brands'));
    }

    /** Brand landing page: the brand's sellable products with the usual filters. */
    public function brand(Request $request, string $locale, string $slug)
    {
        $brand = Brand::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return $this->render($request, null, null, ['title' => $brand->name, 'action' => route('storefront.brand.show', $brand->slug), 'image' => $brand->logo_url, 'description' => null],
            fn ($p) => $p->where('brand_id', $brand->id));
    }

    public function tag(Request $request, string $locale, string $slug)
    {
        $tag = Tag::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return $this->render($request, null, null, ['title' => $tag->name, 'action' => route('storefront.tag.show', $tag->slug), 'image' => null, 'description' => null],
            fn ($p) => $p->whereHas('tags', fn ($t) => $t->where('tags.id', $tag->id)));
    }

    public function results(Request $request)
    {
        return $this->render($request, null, CatalogFilter::fromRequest($request)->q ?? '');
    }

    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        return response()->json(mb_strlen($q) < 2 ? ['products' => [], 'categories' => []] : $this->search->suggest($q));
    }

    private function render(Request $request, ?Category $category, ?string $q, ?array $listing = null, ?\Closure $narrow = null)
    {
        $filter = CatalogFilter::fromRequest($request);
        $scope = $this->search->scope($category, $q === null ? null : ($filter->q ?: null));
        if ($narrow) {
            $scope->whereHas('product', $narrow);
        }
        if ($q !== null && $filter->q === null) {
            $scope->whereRaw('1 = 0');
        }
        $items = $this->search->paginate($scope, $filter);
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'html' => view('storefront._grid', ['items' => $items])->render(),
                'pagination' => (string) $items->links('storefront._pagination'),
                'count' => $items->total(), 'label' => __('store.results_count', ['n' => $items->total()]),
            ]);
        }

        return view('storefront.catalog', [
            'category' => $category, 'filter' => $filter, 'items' => $items, 'facets' => $this->search->facets($scope),
            'listing' => $listing, 'crumbs' => $category?->breadcrumbs() ?? collect(), 'children' => $category ? $category->children()->where('is_active', true)->get() : collect(),
        ]);
    }
}
