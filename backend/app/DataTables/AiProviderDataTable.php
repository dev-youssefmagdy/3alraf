<?php

namespace App\DataTables;

use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class AiProviderDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return AiProvider::query()->withCount('models')->orderByDesc('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name', 'title' => __('ai.fields.name')],
            ['data' => 'driver_label', 'title' => __('ai.fields.driver'), 'orderable' => false, 'searchable' => false],
            ['data' => 'models_count', 'title' => __('ai.menu.models'), 'searchable' => false],
            ['data' => 'test_status', 'title' => __('ai.connection'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $u = auth('central')->user();
        $dt->editColumn('name', fn (AiProvider $p) => e($p->name))
            ->addColumn('driver_label', fn (AiProvider $p) => e($p->driver->label()))
            ->addColumn('test_status', fn (AiProvider $p) => $p->last_tested_at ? $this->badge((bool) $p->last_test_ok, __('ai.test_ok'), __('ai.test_failed')) : '—')
            ->addColumn('status', fn (AiProvider $p) => $this->badge($p->is_active))
            ->addColumn('actions', function (AiProvider $p) use ($u) {
                $h = $this->actionButtons(route('central.ai.providers.edit', $p), route('central.ai.providers.destroy', $p), (bool) $u?->can('ai_providers.edit'), (bool) $u?->can('ai_providers.delete'));
                if ($u?->can('ai_providers.edit')) {
                    $h = '<div class="flex items-center gap-2"><button type="button" data-ai-test="'.e(route('central.ai.providers.test', $p)).'" class="btn-sm btn-secondary">'.e(__('ai.test_connection')).'</button></div>'.$h;
                }

                return $h;
            });
    }

    protected function rawColumns(): array
    {
        return ['name', 'driver_label', 'test_status', 'status', 'actions'];
    }
}
