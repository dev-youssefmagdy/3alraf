<?php

namespace App\DataTables;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class BannerDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return Banner::query()->orderBy('placement')->orderBy('sort_order')->orderBy('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'thumb', 'title' => '', 'orderable' => false, 'searchable' => false],
            ['data' => 'title_label', 'title' => __('ops.fields.title'), 'orderable' => false, 'searchable' => false],
            ['data' => 'placement_label', 'title' => __('ops.fields.placement'), 'orderable' => false, 'searchable' => false],
            ['data' => 'schedule', 'title' => __('ops.fields.schedule'), 'orderable' => false, 'searchable' => false],
            ['data' => 'sort_order', 'title' => __('admin.fields.sort_order')],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('thumb', fn (Banner $m) => ($u = $m->imageUrl()) ? '<img src="'.e($u).'" class="h-10 w-20 rounded object-cover" alt="">' : '')
            ->addColumn('title_label', fn (Banner $m) => e($m->title ?? '—'))
            ->addColumn('placement_label', fn (Banner $m) => e($m->placement->label()))
            ->addColumn('schedule', fn (Banner $m) => e(($m->starts_at?->format('Y-m-d H:i') ?? '…').' → '.($m->ends_at?->format('Y-m-d H:i') ?? '…')))
            ->addColumn('status', fn (Banner $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (Banner $m) => $this->tenantActions($m, 'tenant.banners', 'banners'));
    }

    protected function rawColumns(): array
    {
        return ['thumb', 'title_label', 'placement_label', 'schedule', 'status', 'actions'];
    }
}
