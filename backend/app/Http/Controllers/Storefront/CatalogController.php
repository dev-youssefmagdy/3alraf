<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Storefront\CatalogFilter;
use App\Http\Controllers\Controller;
use App\Models\Category;
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

    public function results(Request $request)
    {
        return $this->render($request, null, CatalogFilter::fromRequest($request)->q ?? '');
    }

    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        return response()->json(mb_strlen($q) < 2 ? ['products' => [], 'categories' => []] : $this->search->suggest($q));
    }

    private function render(Request $request, ?Category $category, ?string $q)
    {
        $filter = CatalogFilter::fromRequest($request);
        $scope = $this->search->scope($category, $q === null ? null : ($filter->q ?: null));
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
            'crumbs' => $category?->breadcrumbs() ?? collect(), 'children' => $category ? $category->children()->where('is_active', true)->get() : collect(),
        ]);
    }
}
