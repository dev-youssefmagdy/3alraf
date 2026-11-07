<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\BranchDataTable;
use App\DTOs\BranchData;
use App\Http\Requests\Tenant\BranchRequest;
use App\Models\Branch;
use App\Services\BranchService;
use Illuminate\Database\Eloquent\Model;

class BranchController extends ResourceController
{
    protected static string $perm = 'branches';
    protected static string $routeBase = 'tenant.branches';
    protected static string $view = 'tenant.branches';
    protected static string $titleKey = 'ops.branches';
    protected static string $requestClass = BranchRequest::class;
    protected static string $dtoClass = BranchData::class;
    protected static string $tableClass = BranchDataTable::class;

    public function __construct(BranchService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new Branch(['is_active' => true]);
    }

    protected function formData(Model $model): array
    {
        return ['cities' => \App\Models\City::where('is_active', true)->orderBy('sort_order')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()];
    }
}
