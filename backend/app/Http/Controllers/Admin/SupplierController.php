<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\SupplierDataTable;
use App\DTOs\SupplierData;
use App\Http\Requests\Admin\SupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Database\Eloquent\Model;

class SupplierController extends ResourceController
{
    protected static string $perm = 'suppliers';
    protected static string $routeBase = 'admin.suppliers';
    protected static string $view = 'admin.suppliers';
    protected static string $titleKey = 'ops.suppliers';
    protected static string $requestClass = SupplierRequest::class;
    protected static string $dtoClass = SupplierData::class;
    protected static string $tableClass = SupplierDataTable::class;

    public function __construct(SupplierService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new Supplier(['is_active' => true]);
    }
}
