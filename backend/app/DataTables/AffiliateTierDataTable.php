<?php

namespace App\DataTables;

use App\Models\AffiliateTier;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class AffiliateTierDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return AffiliateTier::query()->orderBy('min_sales');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name', 'title' => __('admin.fields.name')],
            ['data' => 'min_sales', 'title' => __('growth.fields.min_sales'), 'searchable' => false],
            ['data' => 'percent', 'title' => __('growth.fields.percent'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->editColumn('min_sales', fn (AffiliateTier $m) => $this->money($m->min_sales))
            ->editColumn('percent', fn (AffiliateTier $m) => rtrim(rtrim((string) $m->percent, '0'), '.').'%')
            ->addColumn('actions', fn (AffiliateTier $m) => $this->tenantActions($m, 'tenant.affiliate-tiers', 'affiliate_tiers'));
    }
}
