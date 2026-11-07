<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\AffiliateTierDataTable;
use App\DTOs\AffiliateTierData;
use App\Http\Requests\Admin\AffiliateTierRequest;
use App\Models\AffiliateTier;
use App\Services\AffiliateTierService;
use Illuminate\Database\Eloquent\Model;

class AffiliateTierController extends ResourceController
{
    protected static string $perm = 'affiliate_tiers';
    protected static string $routeBase = 'admin.affiliate-tiers';
    protected static string $view = 'admin.affiliate-tiers';
    protected static string $titleKey = 'growth.tiers';
    protected static string $requestClass = AffiliateTierRequest::class;
    protected static string $dtoClass = AffiliateTierData::class;
    protected static string $tableClass = AffiliateTierDataTable::class;

    public function __construct(AffiliateTierService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new AffiliateTier(['min_sales' => 0, 'percent' => 5]);
    }
}
