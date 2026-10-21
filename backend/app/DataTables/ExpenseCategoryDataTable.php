<?php

namespace App\DataTables;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class ExpenseCategoryDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return ExpenseCategory::query()->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name_label', 'title' => __('admin.fields.name'), 'orderable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('name_label', fn (ExpenseCategory $m) => e($m->name))
            ->filterColumn('name_label', fn ($q, $k) => $q->where('name', 'like', "%$k%"))
            ->addColumn('status', fn (ExpenseCategory $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (ExpenseCategory $m) => $this->tenantActions($m, 'tenant.expense-categories', 'expense_categories'));
    }

    protected function rawColumns(): array
    {
        return ['name_label', 'status', 'actions'];
    }
}
