<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\ShippingCompanyDataTable;
use App\DTOs\ShippingCompanyData;
use App\Http\Requests\Tenant\ShippingCompanyRequest;
use App\Models\ShippingCompany;
use App\Services\ShippingCompanyService;
use Illuminate\Database\Eloquent\Model;

class ShippingCompanyController extends ResourceController
{
    protected static string $perm = 'shipping';
    protected static string $routeBase = 'tenant.shipping-companies';
    protected static string $view = 'tenant.shipping-companies';
    protected static string $titleKey = 'ops.shipping_companies';
    protected static string $requestClass = ShippingCompanyRequest::class;
    protected static string $dtoClass = ShippingCompanyData::class;
    protected static string $tableClass = ShippingCompanyDataTable::class;

    public function __construct(ShippingCompanyService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new ShippingCompany(['is_active' => true, 'sort_order' => 0]);
    }

    protected function formData(Model $model): array
    {
        $model->load('rates');

        return ['cities' => \App\Models\City::where('is_active', true)->orderBy('sort_order')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()];
    }
}
