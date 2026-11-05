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

    protected function formData(Model $model): array
    {
        if (! auth('tenant')->user()?->can('erp.expenses.manage')) {
            return ['erpAccounts' => []];
        }
        if (! \App\Models\Erp\Account::exists()) {
            app(\App\Services\Erp\Accounting\CoaTemplate::class)->seed(current_tenant());
        }

        return ['erpAccounts' => \App\Models\Erp\Account::where('is_group', false)->where('is_active', true)->whereIn('type', ['expense', 'asset'])
            ->where(fn ($q) => $q->where('type', 'expense')->orWhereIn('role_key', ['packaging_supplies', 'prepaid']))->orderBy('code')->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->code.' - '.$a->name])->all()];
    }

    protected function blank(): Model
    {
        return new ExpenseCategory(['is_active' => true]);
    }
}
