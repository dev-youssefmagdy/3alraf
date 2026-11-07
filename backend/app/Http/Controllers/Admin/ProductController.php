<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\ProductDataTable;
use App\DTOs\ActivateProductsData;
use App\DTOs\ProductData;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateProductsRequest;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Requests\Qr\QrDownloadRequest;
use App\Models\Brand;
use App\Models\Language;
use App\Models\Product;
use App\Repositories\Contracts\AttributeRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Services\CatalogService;
use App\Services\ProductService;
use App\Services\Qr\QrCodeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductController extends Controller implements HasMiddleware
{
    public function __construct(
        private ProductService $service,
        private CategoryRepositoryInterface $categories,
        private AttributeRepositoryInterface $attributes,
        private CatalogService $catalog,
        private QrCodeManager $qr,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:products.view', only: ['index', 'datatable', 'qr']),
            new Middleware('can:products.create', only: ['create', 'store', 'available', 'bulkStore', 'validateBulk']),
            new Middleware('can:products.edit', only: ['edit', 'update', 'validateForm', 'regenerateQr']),
            new Middleware('can:products.delete', only: ['destroy', 'unlist']),
        ];
    }

    public function index()
    {
        return view('admin.products.index', ['table' => new ProductDataTable]);
    }

    public function datatable(ProductDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    public function create()
    {
        return view('admin.products.form', $this->formData(new Product(['type' => ProductType::SIMPLE, 'is_active' => true])));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $this->service->create(ProductData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('admin.products.index'));
    }

    public function edit(Product $product)
    {
        $product->load(['images', 'categories', 'tags', 'variants.values', 'variants.images']);

        return view('admin.products.form', $this->formData($product) + ['qrCodes' => $this->qr->listingCodes($product)]);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $this->service->update($product, ProductData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('admin.products.index'));
    }

    /** "Activate products": products without a price yet, with inline price inputs. */
    public function available(Request $request)
    {
        $q = $request->string('q')->toString();
        $products = $this->catalog->inactiveQuery()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%$q%")->orWhere('sku', 'like', "%$q%")))
            ->orderBy('name')->paginate(50)->withQueryString();

        return view('admin.products.available', ['products' => $products, 'q' => $q]);
    }

    public function validateBulk(ActivateProductsRequest $request): JsonResponse
    {
        return $this->ok();
    }

    public function bulkStore(ActivateProductsRequest $request): JsonResponse
    {
        $n = $this->catalog->enableBulk(ActivateProductsData::fromRequest($request));

        return $this->ok(__('admin.products_enabled', ['count' => $n]), route('admin.products.index'));
    }

    /** PNG download of the product QR (or `?variant={product variant id}`); generated on demand when missing. */
    public function qr(QrDownloadRequest $request, Product $product)
    {
        $file = $this->qr->download($product, $request->variantId());
        abort_if($file === null, 404);

        return response($file[0], 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'attachment; filename="'.$file[1].'"', 'Cache-Control' => 'private, no-store']);
    }

    /** Rebuild the product QR (or `?variant={product variant id}`) from scratch. */
    public function regenerateQr(QrDownloadRequest $request, Product $product): JsonResponse
    {
        $vid = $request->variantId();
        $variant = $vid ? $product->variants()->find($vid) : null;
        abort_if($vid && ! $variant, 404);
        abort_unless($this->qr->regenerate($product, $variant), 404);

        return $this->ok(__('admin.qr_regenerated'));
    }

    /** Take the product off sale (inactive, price cleared, QR removed); the product itself stays. */
    public function unlist(Product $product): JsonResponse
    {
        $this->catalog->delete($product);

        return $this->ok(__('admin.taken_off_sale'));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->service->delete($product);

        return $this->ok(__('admin.deleted'));
    }

    public function validateForm(ProductRequest $request, ?Product $product = null): JsonResponse
    {
        return $this->ok();
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'languages' => Language::activeList(),
            'types' => ProductType::options(),
            'brands' => Brand::orderBy('sort_order')->get()->pluck('name', 'id')->all(),
            'tags' => \App\Models\Tag::orderBy('sort_order')->get()->pluck('name', 'id')->all(),
            'categoryTree' => $this->categories->flatTree(),
            'attributes' => $this->attributes->withValues(),
        ];
    }
}
