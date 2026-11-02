<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\BrandDetailResource;
use App\Http\Resources\Api\BrandResource;
use App\Http\Resources\Api\CategoryDetailResource;
use App\Http\Resources\Api\TagResource;
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
use App\Models\Tag;
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

    /** Category / brand / tag page or search: ?category=slug &brand=slug &tag=slug &q= &brands[]= &values[]= &price_min= &price_max= &sort= &per_page= &page= */
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

    /** Live fixed-price bundles whose components are all purchasable (add one with POST cart/items {bundle_id, quantity}). */
    public function bundles(\App\Services\Storefront\BundleCatalog $catalog): JsonResponse
    {
        return $this->list(\App\Http\Resources\Api\BundleResource::collection(collect($catalog->listing())));
    }

    public function bundle(string $slug, \App\Services\Storefront\BundleCatalog $catalog): JsonResponse
    {
        return $this->respond(new \App\Http\Resources\Api\BundleResource($catalog->find($slug) ?? abort(404)));
    }

    public function brands(): JsonResponse
    {
        return $this->list(BrandResource::collection(Brand::query()->where('is_active', true)
            ->whereHas('products', $this->sellable())
            ->orderBy('sort_order')->get()));
    }

    /** Brand page header (name/logo/product count); its products: GET catalog?brand={slug}. */
    public function brand(string $slug): JsonResponse
    {
        $brand = Brand::where('slug', $slug)->where('is_active', true)->withCount(['products as sellable_count' => $this->sellable()])->firstOrFail();

        return $this->respond(new BrandDetailResource($brand));
    }

    /** Category page header (name/image/description/breadcrumbs/children); its products: GET catalog?category={slug}. */
    public function category(string $slug): JsonResponse
    {
        return $this->respond(new CategoryDetailResource(Category::where('slug', $slug)->where('is_active', true)->firstOrFail()));
    }

    /** Tag page header; its products: GET catalog?tag={slug}. */
    public function tag(string $slug): JsonResponse
    {
        return $this->respond(new TagResource(Tag::where('slug', $slug)->where('is_active', true)->firstOrFail()));
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
        $scope = $this->search->scope($category, $filter->q);
        if ($request->filled('brand')) {
            $brand = Brand::where('slug', (string) $request->query('brand'))->where('is_active', true)->firstOrFail();
            $scope->whereHas('product', fn ($p) => $p->where('brand_id', $brand->id));
        }
        if ($request->filled('tag')) {
            $tag = Tag::where('slug', (string) $request->query('tag'))->where('is_active', true)->firstOrFail();
            $scope->whereHas('product', fn ($p) => $p->whereHas('tags', fn ($t) => $t->where('tags.id', $tag->id)));
        }

        return [$scope, $filter];
    }

    private function sellable(): \Closure
    {
        return fn ($p) => $p->where('is_active', true)->whereIn('id', TenantProduct::where('is_active', true)->select('product_id'));
    }
}
