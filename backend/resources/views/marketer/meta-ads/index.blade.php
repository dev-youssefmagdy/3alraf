@php
    $t = fn ($k, $r = []) => __('marketing.meta.'.$k, $r);
    $S = \App\Enums\Marketing\MetaDraftStatus::class;
@endphp
<x-layouts.marketer :title="$t('title')">
    <div class="space-y-4" data-meta-index>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-bold text-slate-800">{{ $t('title') }}</h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('marketer.meta-ads.insights') }}" class="btn btn-secondary" data-action="insights">{{ $t('btn.insights') }}</a>
                @can('meta_ads.draft')<a href="{{ route('marketer.meta-ads.create') }}" class="btn btn-primary" data-action="create">{{ $t('btn.create') }}</a>@endcan
            </div>
        </div>
        <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="note" data-safety-notice>{{ $t('safety_notice') }}</p>
        @unless ($meta['status']['configured'])
            <p class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700" role="status" data-not-configured>{{ $t('not_configured') }}</p>
        @endunless
        @if ($meta['error'])<p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert" data-meta-error>{{ $meta['error'] }}</p>@endif

        @if ($meta['account'])
            <div class="grid gap-4 lg:grid-cols-3">
                <x-card :title="$t('account')">
                    <dl class="space-y-1 text-sm" data-account>
                        <div class="break-words font-semibold">{{ $meta['account']['name'] }}</div>
                        <div class="text-slate-500" dir="ltr">{{ $meta['account']['id'] }} · {{ $meta['account']['currency'] }}</div>
                        <div class="text-slate-500">{{ $t('graph_version') }}: <span dir="ltr">{{ $meta['status']['graph_version'] }}</span></div>
                    </dl>
                </x-card>
                <x-card :title="$t('insights_30d')" class="lg:col-span-2">
                    @if ($meta['insights'])
                        <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4" data-insights-summary>
                            @foreach (['spend', 'impressions', 'reach', 'clicks', 'ctr', 'cpc', 'cpm', 'roas'] as $k)
                                <div><div class="text-xs text-slate-500">{{ $t('insights.'.$k) }}</div><div class="font-semibold" dir="ltr">{{ $meta['insights'][$k] ?? '—' }}</div></div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500">{{ $t('none') }}</p>
                    @endif
                </x-card>
            </div>
        @endif
        @if ($meta['campaigns'])
            <x-card :title="$t('campaigns_live')">
                <div class="overflow-x-auto" data-live-campaigns>
                    <table class="w-full text-sm">
                        <thead class="text-xs text-slate-500"><tr><th class="py-1 text-start">{{ $t('fields.name') }}</th><th class="py-1 text-start">{{ $t('fields.objective') }}</th><th class="py-1 text-start">{{ $t('fields.status') }}</th></tr></thead>
                        <tbody>@foreach ($meta['campaigns'] as $c)<tr class="border-t border-slate-100"><td class="py-1.5">{{ $c['name'] }}</td><td class="py-1.5" dir="ltr">{{ $c['objective'] }}</td><td class="py-1.5" dir="ltr">{{ $c['effective_status'] ?? $c['status'] }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </x-card>
        @endif

        <x-card :title="$t('drafts')">
            <form method="GET" class="mb-3 flex flex-wrap items-center gap-2" data-draft-filters>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control min-w-0 flex-1 sm:max-w-xs" aria-label="{{ $t('fields.name') }}" placeholder="{{ $t('fields.name') }}">
                <select name="status" class="form-control w-auto min-w-0" aria-label="{{ $t('fields.status') }}">
                    <option value="">—</option>
                    @foreach ($S::cases() as $s)<option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }} ({{ $counts[$s->value] ?? 0 }})</option>@endforeach
                </select>
                <x-button type="submit">{{ $t('btn.filter') }}</x-button>
            </form>
            @if ($drafts->isEmpty())
                <p class="py-6 text-center text-sm text-slate-500" data-drafts-empty>{{ $t('empty') }}</p>
            @else
                <div class="overflow-x-auto" data-drafts-table>
                    <table class="w-full text-sm">
                        <thead class="text-xs text-slate-500"><tr>
                            <th class="py-1 text-start">{{ $t('fields.name') }}</th><th class="py-1 text-start">{{ $t('fields.objective') }}</th><th class="py-1 text-start">{{ $t('fields.budget_amount') }}</th>
                            <th class="py-1 text-start">{{ $t('fields.status') }}</th><th class="py-1 text-start">{{ $t('fields.version') }}</th><th class="py-1 text-start">{{ $t('fields.updated_at') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($drafts as $d)
                                <tr class="border-t border-slate-100" data-draft="{{ $d->id }}">
                                    <td class="py-1.5"><a class="font-medium text-brand-700 hover:underline" href="{{ route('marketer.meta-ads.show', $d->id) }}">{{ $d->name }}</a></td>
                                    <td class="py-1.5">{{ $t('objectives.'.$d->objective) }}</td>
                                    <td class="whitespace-nowrap py-1.5" dir="ltr">{{ $d->budget_amount }} {{ $d->currency }} / {{ $t('budget_types.'.$d->budget_type) }}</td>
                                    <td class="py-1.5"><span class="badge {{ $d->status->badge() }}">{{ $d->status->label() }}</span></td>
                                    <td class="py-1.5">{{ $d->version }}</td>
                                    <td class="whitespace-nowrap py-1.5" dir="ltr">{{ $d->updated_at?->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $drafts->links() }}</div>
            @endif
        </x-card>
    </div>
</x-layouts.marketer>
