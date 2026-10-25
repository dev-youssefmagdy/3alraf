<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\BundleDataTable;
use App\DTOs\BundleData;
use App\Http\Requests\Tenant\BundleRequest;
use App\Models\Bundle;
use App\Services\BundleService;
use App\Services\Stock\StockItemCatalog;
use Illuminate\Database\Eloquent\Model;

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

    protected function blank(): Model
    {
        return new Bundle(['is_active' => true, 'sort_order' => 0]);
    }

    protected function formData(Model $model): array
    {
        $model->loadMissing('items');

        return ['catalogItems' => app(StockItemCatalog::class)->options()];
    }
}
