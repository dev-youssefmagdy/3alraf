<?php

namespace App\DataTables;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class ExpenseDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Expense::query()->with(['category', 'branch'])->latest('expense_date')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'expense_date', 'title' => __('ops.fields.date')],
            ['data' => 'category_label', 'title' => __('ops.fields.category'), 'orderable' => false, 'searchable' => false],
            ['data' => 'branch_label', 'title' => __('ops.fields.branch'), 'orderable' => false, 'searchable' => false],
            ['data' => 'amount', 'title' => __('ops.fields.amount')],
            ['data' => 'note', 'title' => __('ops.fields.note'), 'orderable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->editColumn('expense_date', fn (Expense $m) => $m->expense_date->toDateString())
            ->addColumn('category_label', fn (Expense $m) => e($m->category?->name))
            ->addColumn('branch_label', fn (Expense $m) => e($m->branch?->name ?? '—'))
            ->editColumn('amount', fn (Expense $m) => $this->money($m->amount))
            ->editColumn('note', fn (Expense $m) => e(\Illuminate\Support\Str::limit((string) $m->note, 60)))
            ->addColumn('actions', fn (Expense $m) => ($m->attachment_url ? '<a href="'.e($m->attachment_url).'" target="_blank" class="btn-sm btn-secondary me-1">'.e(__('ops.fields.attachment')).'</a>' : '').$this->tenantActions($m, 'tenant.expenses', 'expenses'));
    }

    protected function rawColumns(): array
    {
        return ['category_label', 'branch_label', 'note', 'actions'];
    }
}
