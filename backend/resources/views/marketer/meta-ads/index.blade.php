@php
    $t = fn ($k, $r = []) => __('marketing.meta.'.$k, $r);
    $S = \App\Enums\Marketing\MetaDraftStatus::class;
@endphp
<x-layouts.marketer :title="$t('title')">
    <x-slot:header>
        <x-ui.page-header :title="$t('title')">
            <a href="{{ route('marketer.meta-ads.insights') }}" class="btn btn-secondary btn-md" data-action="insights">{{ $t('btn.insights') }}</a>
            @can('meta_ads.draft')<a href="{{ route('marketer.meta-ads.create') }}" class="btn btn-primary btn-md" data-action="create">{{ $t('btn.create') }}</a>@endcan
        </x-ui.page-header>
    </x-slot:header>
    <div class="space-y-4" data-meta-index>
        <x-ui.alert tone="success" data-safety-notice>{{ $t('safety_notice') }}</x-ui.alert>
        @unless ($meta['status']['configured'])
            <x-ui.alert tone="info" data-not-configured>{{ $t('not_configured') }}</x-ui.alert>
        @endunless
        @if ($meta['error'])<x-ui.alert tone="danger" data-meta-error>{{ $meta['error'] }}</x-ui.alert>@endif

        @if ($meta['account'])
            <div class="grid gap-4 lg:grid-cols-3">
                <x-ui.card :title="$t('account')">
                    <dl class="space-y-1 text-sm" data-account>
                        <div class="break-words font-semibold">{{ $meta['account']['name'] }}</div>
                        <div class="text-panel-text-muted" dir="ltr">{{ $meta['account']['id'] }} · {{ $meta['account']['currency'] }}</div>
                        <div class="text-panel-text-muted">{{ $t('graph_version') }}: <span dir="ltr">{{ $meta['status']['graph_version'] }}</span></div>
                    </dl>
                </x-ui.card>
                <x-ui.card :title="$t('insights_30d')" class="lg:col-span-2">
                    @if ($meta['insights'])
                        <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4" data-insights-summary>
                            @foreach (['spend', 'impressions', 'reach', 'clicks', 'ctr', 'cpc', 'cpm', 'roas'] as $k)
                                <div><div class="text-xs text-panel-text-muted">{{ $t('insights.'.$k) }}</div><div class="font-semibold" dir="ltr">{{ $meta['insights'][$k] ?? '—' }}</div></div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-panel-text-muted">{{ $t('none') }}</p>
                    @endif
                </x-ui.card>
            </div>
        @endif
        @if ($meta['campaigns'])
            <x-ui.card :title="$t('campaigns_live')">
                <x-ui.table data-live-campaigns>
                    <thead><tr><th>{{ $t('fields.name') }}</th><th>{{ $t('fields.objective') }}</th><th>{{ $t('fields.status') }}</th></tr></thead>
                    <tbody>@foreach ($meta['campaigns'] as $c)<tr><td data-label="">{{ $c['name'] }}</td><td data-label="{{ $t('fields.objective') }}" dir="ltr">{{ $c['objective'] }}</td><td data-label="{{ $t('fields.status') }}" dir="ltr">{{ $c['effective_status'] ?? $c['status'] }}</td></tr>@endforeach</tbody>
                </x-ui.table>
            </x-ui.card>
        @endif

        <x-ui.card :title="$t('drafts')">
            <form method="GET" class="mb-3 flex flex-wrap items-center gap-2" data-draft-filters>
                <x-ui.search name="q" :value="request('q')" :placeholder="$t('fields.name')" class="min-w-0 flex-1 sm:max-w-xs" />
                <select name="status" class="form-control w-auto min-w-0" aria-label="{{ $t('fields.status') }}">
                    <option value="">—</option>
                    @foreach ($S::cases() as $s)<option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }} ({{ $counts[$s->value] ?? 0 }})</option>@endforeach
                </select>
                <x-ui.button type="submit">{{ $t('btn.filter') }}</x-ui.button>
            </form>
            @if ($drafts->isEmpty())
                <x-ui.empty-state kind="nodata" :title="$t('empty')" message="" class="!py-6" data-drafts-empty />
            @else
                <x-ui.table data-drafts-table>
                    <thead><tr>
                        <th>{{ $t('fields.name') }}</th><th>{{ $t('fields.objective') }}</th><th>{{ $t('fields.budget_amount') }}</th>
                        <th>{{ $t('fields.status') }}</th><th>{{ $t('fields.version') }}</th><th>{{ $t('fields.updated_at') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($drafts as $d)
                            <tr data-draft="{{ $d->id }}">
                                <td data-label=""><a class="font-semibold text-panel-accent-text hover:underline" href="{{ route('marketer.meta-ads.show', $d->id) }}">{{ $d->name }}</a></td>
                                <td data-label="{{ $t('fields.objective') }}">{{ $t('objectives.'.$d->objective) }}</td>
                                <td data-label="{{ $t('fields.budget_amount') }}" class="whitespace-nowrap" dir="ltr">{{ $d->budget_amount }} {{ $d->currency }} / {{ $t('budget_types.'.$d->budget_type) }}</td>
                                <td data-label="{{ $t('fields.status') }}"><x-ui.badge size="sm" :tone="$d->status->badge()">{{ $d->status->label() }}</x-ui.badge></td>
                                <td data-label="{{ $t('fields.version') }}">{{ $d->version }}</td>
                                <td data-label="{{ $t('fields.updated_at') }}" class="whitespace-nowrap" dir="ltr">{{ $d->updated_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
                <div class="mt-3">{{ $drafts->links() }}</div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.marketer>
