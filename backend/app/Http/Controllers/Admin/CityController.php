<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\CityDataTable;
use App\DTOs\CityData;
use App\Http\Requests\Tenant\CityRequest;
use App\Models\City;
use App\Services\CityService;
use Illuminate\Database\Eloquent\Model;

class CityController extends ResourceController
{
    protected static string $perm = 'cities';
    protected static string $routeBase = 'tenant.cities';
    protected static string $view = 'tenant.cities';
    protected static string $titleKey = 'ops.cities';
    protected static string $requestClass = CityRequest::class;
    protected static string $dtoClass = CityData::class;
    protected static string $tableClass = CityDataTable::class;

    public function __construct(CityService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new City(['is_active' => true, 'sort_order' => 0]);
    }
}
