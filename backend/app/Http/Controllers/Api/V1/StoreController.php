<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\BrandResource;
use App\Http\Resources\Api\CustomPageResource;
use App\Http\Resources\Api\FacetsResource;
use App\Http\Resources\Api\HomeResource;
use App\Http\Resources\Api\MenuNodeResource;
use App\Http\Resources\Api\ProductCardResource;
use App\Http\Resources\Api\ProductDetailResource;
use App\Http\Resources\Api\StaticPageDetailResource;
use App\Http\Resources\Api\StaticPageResource;
use App\Http\Resources\Api\StoreResource;
use App\Http\Resources\Api\SuggestResource;
use App\DTOs\Storefront\CatalogFilter;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomPage;
use App\Models\StaticPage;
use App\Models\TenantProduct;
use App\Services\Storefront\CardPresenter;
use App\Services\Storefront\CatalogSearch;
use App\Services\Storefront\CustomPageProducts;
use App\Services\Storefront\ProductDetail;
use App\Services\Storefront\StorefrontData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Public, read-only storefront data: store info, home, menu, catalog, product, brands, static pages. */
class StoreController extends ApiController
{
    public function __construct(private StorefrontData $data, private CatalogSearch $search) {}

    public function store(): JsonResponse
    {
        return $this->respond(new StoreResource(current_tenant()));
    }

    public function home(): JsonResponse
    {
        return $this->respond(new HomeResource($this->data->home()));
    }

    public function menu(): JsonResponse
    {
        return $this->list(MenuNodeResource::collection($this->data->menu()));
    }

    /** Category page / search: ?category=slug &q= &brands[]= &values[]= &price_min= &price_max= &sort= &per_page= &page= */
    public function catalog(Request $request): JsonResponse
    {
        [$scope, $filter] = $this->scope($request);

        return $this->page($this->search->paginate($scope, $filter), ProductCardResource::class);
    }

    /** Filter sidebar data for the same scope (brands, attribute values, price range, sort keys). */
    public function facets(Request $request): JsonResponse
    {
        return $this->respond(new FacetsResource($this->search->facets($this->scope($request)[0])));
    }

    public function suggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        return $this->respond(new SuggestResource(mb_strlen($q) < 2 ? ['products' => [], 'categories' => []] : $this->search->suggest($q)));
    }

    public function product(string $slug, ProductDetail $detail): JsonResponse
    {
        $d = $detail->load($slug, auth('web')->user());

        return $this->respond(new ProductDetailResource([
            'product' => $d['product'], 'tp' => $d['tp'], 'picker' => $d['picker'], 'summary' => $d['summary'], 'reviews' => $d['reviews'],
            'related' => $d['related'], 'in_wishlist' => $d['inWishlist'], 'can_review' => $d['canReview'],
        ]));
    }

    public function brands(): JsonResponse
    {
        return $this->list(BrandResource::collection(Brand::query()->where('is_active', true)
            ->whereHas('products', fn ($p) => $p->where('is_active', true)->whereIn('id', TenantProduct::where('is_active', true)->select('product_id')))
            ->orderBy('sort_order')->get()));
    }

    public function pages(): JsonResponse
    {
        return $this->list(StaticPageResource::collection(StaticPage::where('is_active', true)->orderBy('id')->get()));
    }

    public function staticPage(string $slug): JsonResponse
    {
        return $this->respond(new StaticPageDetailResource(StaticPage::where('slug', $slug)->where('is_active', true)->firstOrFail()));
    }

    /** Active custom landing pages (slug + title + web URL). */
    public function customPages(): JsonResponse
    {
        return $this->list(CustomPageResource::collection(CustomPage::where('is_active', true)->orderBy('id')->get()));
    }

    /** One custom page by slug + its product grid (pinned products first, then the page filters). Query: price_min, price_max, sort, page. */
    public function customPage(Request $request, string $slug, CustomPageProducts $products, CardPresenter $cards): JsonResponse
    {
        $page = CustomPage::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $refine = array_filter([
            'price_min' => is_numeric($request->query('price_min')) ? (string) $request->query('price_min') : null,
            'price_max' => is_numeric($request->query('price_max')) ? (string) $request->query('price_max') : null,
            'sort' => in_array($request->query('sort'), ['newest', 'price_asc', 'price_desc', 'best_selling'], true) ? $request->query('sort') : null,
        ], fn ($v) => $v !== null);
        $items = $products->paginate($page, $refine);
        $cards->decorate($items->getCollection());

        return $this->respond([
            'page' => (new CustomPageResource($page))->resolve($request),
            'products' => ProductCardResource::collection($items->getCollection())->resolve($request),
        ], null, ['pagination' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage(), 'total' => $items->total()]]);
    }

    /** @return array{0:\Illuminate\Database\Eloquent\Builder,1:CatalogFilter} */
    private function scope(Request $request): array
    {
        $filter = CatalogFilter::fromRequest($request);
        $category = $request->filled('category') ? Category::where('slug', (string) $request->query('category'))->where('is_active', true)->firstOrFail() : null;

        return [$this->search->scope($category, $filter->q), $filter];
    }
}
