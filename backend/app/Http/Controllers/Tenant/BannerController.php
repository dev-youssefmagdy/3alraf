<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\BannerDataTable;
use App\DTOs\BannerData;
use App\Http\Requests\Tenant\BannerRequest;
use App\Models\Banner;
use App\Services\BannerService;
use Illuminate\Database\Eloquent\Model;

class BannerController extends ResourceController
{
    protected static string $perm = 'banners';
    protected static string $routeBase = 'tenant.banners';
    protected static string $view = 'tenant.banners';
    protected static string $titleKey = 'ops.banners';
    protected static string $requestClass = BannerRequest::class;
    protected static string $dtoClass = BannerData::class;
    protected static string $tableClass = BannerDataTable::class;

    public function __construct(BannerService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new Banner(['is_active' => true, 'sort_order' => 0]);
    }
}
