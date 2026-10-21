<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\ExpenseDataTable;
use App\DTOs\ExpenseData;
use App\Http\Requests\Tenant\ExpenseRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Database\Eloquent\Model;

class ExpenseController extends ResourceController
{
    protected static string $perm = 'expenses';
    protected static string $routeBase = 'tenant.expenses';
    protected static string $view = 'tenant.expenses';
    protected static string $titleKey = 'ops.expenses';
    protected static string $requestClass = ExpenseRequest::class;
    protected static string $dtoClass = ExpenseData::class;
    protected static string $tableClass = ExpenseDataTable::class;

    public function __construct(ExpenseService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new Expense(['expense_date' => now()]);
    }

    protected function formData(Model $model): array
    {
        return [
            'categories' => \App\Models\ExpenseCategory::where('is_active', true)->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
            'branches' => \App\Models\Branch::where('is_active', true)->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
        ];
    }
}
