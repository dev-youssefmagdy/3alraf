<?php

namespace App\DataTables;

use App\Models\MarketingApiToken;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

class MarketingApiTokenDataTable extends BaseDataTable
{
    public function query(): Builder
    {
        return MarketingApiToken::query()->with('creator:id,name')->latest('id');
    }

    public function columns(): array
    {
        return [
            ['data' => 'name', 'title' => __('marketing.tokens.name')],
            ['data' => 'token_prefix', 'title' => __('marketing.tokens.prefix')],
            ['data' => 'scopes_label', 'title' => __('marketing.tokens.scopes'), 'orderable' => false, 'searchable' => false],
            ['data' => 'creator_name', 'title' => __('marketing.tokens.created_by'), 'orderable' => false, 'searchable' => false],
            ['data' => 'created_at', 'title' => __('marketing.tokens.created_at')],
            ['data' => 'last_used_at', 'title' => __('marketing.tokens.last_used')],
            ['data' => 'expires_at', 'title' => __('marketing.tokens.expires')],
            ['data' => 'state', 'title' => __('admin.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function rawColumns(): array
    {
        return ['actions', 'state'];
    }

    protected function tweak(EloquentDataTable $dt): void
    {
        $dt->addColumn('scopes_label', fn (MarketingApiToken $t) => implode(', ', $t->scopes ?? []))
            ->addColumn('creator_name', fn (MarketingApiToken $t) => $t->creator?->name ?? '—')
            ->editColumn('created_at', fn (MarketingApiToken $t) => $t->created_at?->format('Y-m-d H:i'))
            ->editColumn('last_used_at', fn (MarketingApiToken $t) => $t->last_used_at?->format('Y-m-d H:i') ?? '—')
            ->editColumn('expires_at', fn (MarketingApiToken $t) => $t->expires_at?->format('Y-m-d') ?? __('marketing.tokens.never'))
            ->addColumn('state', fn (MarketingApiToken $t) => '<span class="badge '.['active' => 'badge-success', 'expired' => 'badge-warn', 'revoked' => 'badge-muted'][$t->state()].'">'.e(__('marketing.tokens.state_'.$t->state())).'</span>')
            ->addColumn('actions', fn (MarketingApiToken $t) => $t->isRevoked() ? '' : '<button type="button" class="btn-sm btn-danger" data-gpost="'.e(route('marketer.tokens.revoke', $t->id)).'" data-confirm="'.e(__('marketing.tokens.revoke_confirm')).'">'.e(__('marketing.tokens.revoke')).'</button>');
    }
}
