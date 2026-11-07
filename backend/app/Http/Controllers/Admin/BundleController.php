<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\BundleDataTable;
use App\DTOs\BundleData;
use App\Http\Requests\Tenant\BundleRequest;
use App\Models\Bundle;
use App\Services\BundleService;
use App\Services\Stock\StockItemCatalog;
use App\Services\Qr\QrCodeManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\Middleware;

class BundleController extends ResourceController
{
    protected static string $perm = 'bundles';
    protected static string $routeBase = 'tenant.bundles';
    protected static string $view = 'tenant.bundles';
    protected static string $titleKey = 'orders.bundles';
    protected static string $requestClass = BundleRequest::class;
    protected static string $dtoClass = BundleData::class;
    protected static string $tableClass = BundleDataTable::class;

    public function __construct(BundleService $service)
    {
        parent::__construct($service);
    }

    public static function middleware(): array
    {
        return array_merge(parent::middleware(), [
            new Middleware('can:bundles.view', only: ['qr']),
            new Middleware('can:bundles.edit', only: ['regenerateQr']),
        ]);
    }

    /** PNG download of the bundle QR; generated on demand when missing. */
    public function qr(QrCodeManager $qr, int $id)
    {
        $file = $qr->bundleDownload(Bundle::query()->findOrFail($id));
        abort_if($file === null, 404);

        return response($file[0], 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'attachment; filename="'.$file[1].'"', 'Cache-Control' => 'private, no-store']);
    }

    public function regenerateQr(QrCodeManager $qr, int $id): JsonResponse
    {
        abort_unless($qr->regenerateBundle(Bundle::query()->findOrFail($id)), 404);

        return $this->ok(__('admin.qr_regenerated'));
    }

    protected function blank(): Model
    {
        return new Bundle(['is_active' => true, 'sort_order' => 0]);
    }

    protected function formData(Model $model): array
    {
        $model->loadMissing('items');

        return [
            'catalogItems' => app(StockItemCatalog::class)->options(),
            'qr' => $model->exists ? app(QrCodeManager::class)->bundleCode($model) : null,
        ];
    }
}
