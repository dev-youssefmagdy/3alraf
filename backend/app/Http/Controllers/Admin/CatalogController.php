<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\CatalogDataTable;
use App\DTOs\ActivateProductsData;
use App\DTOs\CatalogItemData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateProductsRequest;
use App\Http\Requests\Admin\CatalogItemRequest;
use App\Http\Requests\Qr\QrDownloadRequest;
use App\Models\Product;
use App\Services\CatalogService;
use App\Services\Qr\QrCodeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Catalog page = the selling side of `products` / `product_variants` (price, sale price, cost, status, QR). */
class CatalogController extends Controller implements HasMiddleware
{
    public function __construct(private CatalogService $service, private QrCodeManager $qr) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:catalog.view', only: ['index', 'datatable', 'qr']),
            new Middleware('can:catalog.create', only: ['available', 'bulkStore', 'validateBulk']),
            new Middleware('can:catalog.edit', only: ['edit', 'update', 'validateForm', 'regenerateQr']),
            new Middleware('can:catalog.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('admin.catalog.index', ['table' => new CatalogDataTable]);
    }

    public function datatable(CatalogDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    /** "Activate products": products without a price yet, with inline price inputs. */
    public function available(Request $request)
    {
        $q = $request->string('q')->toString();
        $products = $this->service->inactiveQuery()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%$q%")->orWhere('sku', 'like', "%$q%")))
            ->orderBy('name')->paginate(50)->withQueryString();

        return view('admin.catalog.available', ['products' => $products, 'q' => $q]);
    }

    public function validateBulk(ActivateProductsRequest $request): JsonResponse
    {
        return $this->ok();
    }

    public function bulkStore(ActivateProductsRequest $request): JsonResponse
    {
        $n = $this->service->enableBulk(ActivateProductsData::fromRequest($request));

        return $this->ok(__('admin.products_enabled', ['count' => $n]), route('admin.catalog.index'));
    }

    public function edit(Product $product)
    {
        $product->load(['images', 'variants.values']);

        return view('admin.catalog.form', ['product' => $product, 'qrCodes' => $this->qr->listingCodes($product)]);
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

    public function update(CatalogItemRequest $request, Product $product): JsonResponse
    {
        $this->service->update($product, CatalogItemData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('admin.catalog.index'));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->service->delete($product);

        return $this->ok(__('admin.deleted'));
    }

    public function validateForm(CatalogItemRequest $request, ?Product $product = null): JsonResponse
    {
        return $this->ok();
    }
}
