<?php

namespace App\Http\Controllers\Tenant;

use App\DataTables\ExpenseCategoryDataTable;
use App\DTOs\ExpenseCategoryData;
use App\Http\Requests\Tenant\ExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategoryController extends ResourceController
{
    protected static string $perm = 'expense_categories';
    protected static string $routeBase = 'tenant.expense-categories';
    protected static string $view = 'tenant.expense-categories';
    protected static string $titleKey = 'ops.expense_categories';
    protected static string $requestClass = ExpenseCategoryRequest::class;
    protected static string $dtoClass = ExpenseCategoryData::class;
    protected static string $tableClass = ExpenseCategoryDataTable::class;

    public function __construct(ExpenseCategoryService $service)
    {
        parent::__construct($service);
    }

    protected function blank(): Model
    {
        return new ExpenseCategory(['is_active' => true]);
    }
}
