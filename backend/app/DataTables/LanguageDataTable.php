<?php

namespace App\DataTables;

use App\Models\Language;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class LanguageDataTable extends BaseDataTable
{
    public function __construct(public string $ajaxUrl = '') {}

    public function query(): Builder
    {
        return Language::query()->orderBy('sort_order')->orderBy('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'code', 'title' => __('admin.fields.code')],
            ['data' => 'name', 'title' => __('admin.fields.name')],
            ['data' => 'native_name', 'title' => __('admin.fields.native_name')],
            ['data' => 'direction_label', 'title' => __('admin.fields.direction'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $user = auth('central')->user();
        $dt->addColumn('direction_label', fn (Language $l) => $l->direction->label())
            ->addColumn('status', fn (Language $l) => $this->badge($l->is_active).($l->is_default ? ' '.$this->badge(true, __('admin.default')) : ''))
            ->addColumn('actions', fn (Language $l) => $this->actionButtons(
                route('central.languages.edit', $l), route('central.languages.destroy', $l),
                $user?->can('languages.edit'), $user?->can('languages.delete') && ! $l->is_default))
            ->rawColumns(['status', 'actions']);
    }

    protected function rawColumns(): array
    {
        return ['status', 'actions'];
    }
}
