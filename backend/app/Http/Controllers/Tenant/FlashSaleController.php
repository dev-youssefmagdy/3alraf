<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\FlashSaleDataTable;
use App\DTOs\FlashSaleData;
use App\Http\Requests\Tenant\FlashSaleRequest;
use App\Models\FlashSale;
use App\Services\FlashSaleService;
use App\Services\Stock\StockItemCatalog;
use Illuminate\Database\Eloquent\Model;

class FlashSaleController extends ResourceController
{
    protected static string $perm = 'flash_sales';
    protected static string $routeBase = 'tenant.flash-sales';
    protected static string $view = 'tenant.flash-sales';
    protected static string $titleKey = 'orders.flash_sales';
    protected static string $requestClass = FlashSaleRequest::class;
    protected static string $dtoClass = FlashSaleData::class;
    protected static string $tableClass = FlashSaleDataTable::class;

    public function __construct(FlashSaleService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new FlashSale(['is_active' => true]);
    }

    protected function formData(Model $model): array
    {
        $model->loadMissing('items');

        return ['catalogItems' => app(StockItemCatalog::class)->options()];
    }
}
