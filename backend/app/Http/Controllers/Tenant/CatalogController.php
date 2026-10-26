<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\TenantProductDataTable;
use App\DTOs\BulkEnableData;
use App\DTOs\TenantProductData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\BulkEnableRequest;
use App\Http\Requests\Tenant\TenantProductRequest;
use App\Models\TenantProduct;
use App\Repositories\Contracts\TenantProductRepositoryInterface;
use App\Http\Requests\Qr\QrDownloadRequest;
use App\Services\Qr\QrCodeManager;
use App\Services\TenantCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CatalogController extends Controller implements HasMiddleware
{
    public function __construct(private TenantCatalogService $service, private TenantProductRepositoryInterface $listings, private QrCodeManager $qr) {}

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
        return view('tenant.catalog.index', ['table' => new TenantProductDataTable]);
    }

    public function datatable(TenantProductDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    /** Central products not yet enabled, with inline price inputs (bulk add). */
    public function available(Request $request)
    {
        return view('tenant.catalog.available', ['products' => $this->listings->available($request->string('q')->toString() ?: null), 'q' => $request->string('q')->toString()]);
    }

    public function validateBulk(BulkEnableRequest $request): JsonResponse
    {
        return $this->ok();
    }

    public function bulkStore(BulkEnableRequest $request): JsonResponse
    {
        $n = $this->service->enableBulk(BulkEnableData::fromRequest($request));

        return $this->ok(__('admin.products_enabled', ['count' => $n]), route('tenant.catalog.index'));
    }

    public function edit(TenantProduct $tenantProduct)
    {
        $this->service->syncVariantRows($tenantProduct);
        $tenantProduct->load(['product.images', 'variants.variant.values']);

        return view('tenant.catalog.form', ['tp' => $tenantProduct, 'qrCodes' => $this->qr->listingCodes($tenantProduct)]);
    }

    /** PNG download of the product QR (or `?variant={tenant variant row id}`); generated on demand when missing. */
    public function qr(QrDownloadRequest $request, TenantProduct $tenantProduct)
    {
        $tenantProduct->load('product');
        $file = $this->qr->tenantDownload($tenantProduct, $request->variantId());
        abort_if($file === null, 404);

        return response($file[0], 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'attachment; filename="'.$file[1].'"', 'Cache-Control' => 'private, no-store']);
    }

    /** Rebuild the product QR (or `?variant={tenant variant row id}`) from scratch. */
    public function regenerateQr(QrDownloadRequest $request, TenantProduct $tenantProduct): JsonResponse
    {
        $vid = $request->variantId();
        $tv = $vid ? $tenantProduct->variants()->find($vid) : null;
        abort_if($vid && ! $tv, 404);
        abort_unless($this->qr->regenerate($tenantProduct, $tv), 404);

        return $this->ok(__('admin.qr_regenerated'));
    }

    public function update(TenantProductRequest $request, TenantProduct $tenantProduct): JsonResponse
    {
        $this->service->update($tenantProduct, TenantProductData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('tenant.catalog.index'));
    }

    public function destroy(TenantProduct $tenantProduct): JsonResponse
    {
        $this->service->delete($tenantProduct);

        return $this->ok(__('admin.deleted'));
    }

    public function validateForm(TenantProductRequest $request, ?TenantProduct $tenantProduct = null): JsonResponse
    {
        return $this->ok();
    }
}
