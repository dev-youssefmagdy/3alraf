<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\PackagingOptionDataTable;
use App\DTOs\PackagingOptionData;
use App\Http\Requests\Tenant\PackagingOptionRequest;
use App\Models\PackagingOption;
use App\Services\PackagingOptionService;
use Illuminate\Database\Eloquent\Model;

class PackagingOptionController extends ResourceController
{
    protected static string $perm = 'packaging';
    protected static string $routeBase = 'tenant.packaging';
    protected static string $view = 'tenant.packaging';
    protected static string $titleKey = 'orders.packaging.title';
    protected static string $requestClass = PackagingOptionRequest::class;
    protected static string $dtoClass = PackagingOptionData::class;
    protected static string $tableClass = PackagingOptionDataTable::class;

    public function __construct(PackagingOptionService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new PackagingOption(['is_active' => true, 'sort_order' => 0, 'price' => 0, 'cost' => 0]);
    }
}
