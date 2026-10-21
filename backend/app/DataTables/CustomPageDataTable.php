<?php

namespace App\DataTables;

use App\Models\CustomPage;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class CustomPageDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return CustomPage::query()->withCount('products')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'title_label', 'title' => __('growth.fields.title'), 'orderable' => false],
            ['data' => 'url', 'title' => 'URL', 'orderable' => false, 'searchable' => false],
            ['data' => 'products_count', 'title' => __('growth.pinned_products'), 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('title_label', fn (CustomPage $m) => e($m->title))
            ->filterColumn('title_label', fn ($q, $k) => $q->where('title', 'like', "%$k%"))
            ->addColumn('url', fn (CustomPage $m) => '<a class="text-brand-600" target="_blank" href="'.e(tenant_url(current_tenant(), '/'.$m->slug)).'">/'.e($m->slug).'</a>')
            ->addColumn('status', fn (CustomPage $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (CustomPage $m) => $this->tenantActions($m, 'tenant.custom-pages', 'custom_pages'));
    }

    protected function rawColumns(): array
    {
        return ['title_label', 'url', 'status', 'actions'];
    }
}
