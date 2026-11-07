<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\CityDataTable;
use App\DTOs\CityData;
use App\Http\Requests\Admin\CityRequest;
use App\Models\City;
use App\Services\CityService;
use Illuminate\Database\Eloquent\Model;

class CityController extends ResourceController
{
    protected static string $perm = 'cities';
    protected static string $routeBase = 'admin.cities';
    protected static string $view = 'admin.cities';
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
