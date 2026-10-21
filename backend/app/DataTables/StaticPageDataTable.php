<?php

namespace App\DataTables;

use App\Models\StaticPage;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class StaticPageDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return StaticPage::query()->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'title_label', 'title' => __('growth.fields.title'), 'orderable' => false],
            ['data' => 'slug', 'title' => __('admin.fields.slug_short')],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('title_label', fn (StaticPage $m) => e($m->title))
            ->filterColumn('title_label', fn ($q, $k) => $q->where('title', 'like', "%$k%"))
            ->addColumn('status', fn (StaticPage $m) => $this->badge($m->is_active))
            ->addColumn('actions', fn (StaticPage $m) => $this->tenantActions($m, 'tenant.static-pages', 'static_pages'));
    }

    protected function rawColumns(): array
    {
        return ['title_label', 'status', 'actions'];
    }
}
