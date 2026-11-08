<?php

namespace App\DataTables;

use App\Models\MarketingApiRequest;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class MarketingApiRequestDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return MarketingApiRequest::query()->with('token:id,name,token_prefix')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'created_at', 'title' => __('marketing.api_log.when')],
            ['data' => 'token_name', 'title' => __('marketing.api_log.token'), 'orderable' => false, 'searchable' => false],
            ['data' => 'method', 'title' => __('marketing.api_log.method')],
            ['data' => 'route', 'title' => __('marketing.api_log.route')],
            ['data' => 'status', 'title' => __('marketing.api_log.status')],
            ['data' => 'duration_ms', 'title' => __('marketing.api_log.duration')],
        ];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->editColumn('created_at', fn (MarketingApiRequest $r) => $r->created_at?->format('Y-m-d H:i:s'))
            ->addColumn('token_name', fn (MarketingApiRequest $r) => $r->token ? $r->token->name.' ('.$r->token->token_prefix.')' : '—');
    }
}
