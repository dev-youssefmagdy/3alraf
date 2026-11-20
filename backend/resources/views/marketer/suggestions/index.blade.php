{{-- Suggestions Center: overview (page = overview) and one page per type group (MCP.md §16.2, §17.1, §24). --}}
@php
    $t = fn ($k, $r = []) => __('marketing.suggestions.'.$k, $r);
    $navKey = $page === 'overview' ? 'nav.suggestions_overview' : 'nav.suggestions_'.$page;
    $title = $page === 'overview' ? $t('title') : __('marketing.'.$navKey);
    $statuses = \App\Enums\Marketing\SuggestionStatus::cases();
    $pageTypes = $page === 'overview' ? \App\Enums\Marketing\SuggestionTypeKey::cases() : \App\Enums\Marketing\SuggestionTypeKey::forPage($page);
    $seoTargets = ['product', 'category', 'brand', 'static_page', 'bundle', 'blog_post', 'custom_page', 'site'];
@endphp
<x-layouts.marketer :title="$title">
    <x-slot:header><x-ui.page-header :title="$title" :subtitle="$t('overview_hint')" /></x-slot:header>
    <div class="space-y-4" data-suggestions-page="{{ $page }}">

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" data-kpis>
            <x-ui.stat-card size="compact" icon="sparkles" :label="$t('kpi.total')" :value="number_format($counts['total'])" data-kpi="total" />
            @foreach (['pending' => ['clock', 'sun'], 'approved' => ['check', 'emerald'], 'rejected' => ['x', 'slate'], 'applied' => ['check', 'emerald'], 'failed' => ['alert', 'slate']] as $st => [$ic, $tn])
                <x-ui.stat-card size="compact" :icon="$ic" :tone="$tn" :label="$t('statuses.'.$st)" :value="number_format($counts['by_status'][$st] ?? 0)" data-kpi="{{ $st }}" />
            @endforeach
        </div>

        @if ($page === 'overview')
            <div class="grid gap-4 lg:grid-cols-3">
                <x-ui.card :title="$t('kpi.by_type')" class="lg:col-span-1">
                    <ul class="divide-y divide-panel-border text-sm" data-by-type>
                        @foreach (\App\Enums\Marketing\SuggestionTypeKey::cases() as $type)
                            <li class="flex items-center justify-between py-1.5">
                                <a href="{{ route('marketer.suggestions.'.$type->page()) }}" class="text-panel-accent-text hover:underline">{{ $type->label() }}</a>
                                <span class="font-semibold text-panel-text">{{ $counts['by_type'][$type->value] ?? 0 }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
                <x-ui.card :title="$t('kpi.recent')" class="lg:col-span-2">
                    @forelse ($recent as $a)
                        <div class="flex flex-wrap items-center gap-x-2 border-b border-panel-border py-1.5 text-sm last:border-0">
                            <span class="text-xs text-panel-text-subtle" dir="ltr">{{ $a->created_at?->format('Y-m-d H:i') }}</span>
                            <x-ui.badge size="sm">{{ $t('actions.'.$a->action) }}</x-ui.badge>
                            @if ($a->suggestion)<a class="min-w-0 truncate text-panel-accent-text hover:underline" href="{{ route('marketer.suggestions.show', $a->suggestion_id) }}">#{{ $a->suggestion_id }} {{ $a->suggestion->title }}</a>@endif
                            <span class="text-xs text-panel-text-muted">{{ $a->actorLabel() }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-panel-text-muted">{{ __('admin.no_data') }}</p>
                    @endforelse
                </x-ui.card>
            </div>
        @endif

        @if ($page === 'themes')
            <x-ui.card :title="$t('themes.versions')">
                <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-panel-text-muted">{{ $t('themes.active') }}:</span>
                    <b data-active-theme>{{ $activeTheme ? $activeTheme->name.' · '.$activeTheme->key.' v'.$activeTheme->version : $t('themes.none') }}</b>
                    @can('ai_theme.approve')
                        @if ($activeTheme)
                            <button type="button" class="btn-sm btn-secondary" data-sugg-post="{{ route('marketer.suggestions.themes.revert') }}" data-confirm="{{ $t('confirm.revert') }}">{{ $t('btn.revert') }}</button>
                            <button type="button" class="btn-sm btn-danger" data-sugg-post="{{ route('marketer.suggestions.themes.off') }}" data-confirm="{{ $t('confirm.defaults') }}">{{ $t('btn.defaults') }}</button>
                        @endif
                    @endcan
                </div>
                <x-ui.table>
                    <thead><tr><th>{{ $t('fields.name') }}</th><th>{{ $t('themes.version') }}</th><th>{{ $t('themes.swatches') }}</th><th>{{ $t('cols.when') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($themeVersions as $v)
                        <tr data-theme-version="{{ $v->id }}">
                            <td data-label="">{{ $v->name }} <span class="text-xs text-panel-text-subtle" dir="ltr">{{ $v->key }}</span> @if ($v->is_active)<x-ui.badge tone="success" size="sm">{{ $t('themes.active') }}</x-ui.badge>@endif</td>
                            <td data-label="{{ $t('themes.version') }}">v{{ $v->version }}</td>
                            <td data-label="{{ $t('themes.swatches') }}"><div class="flex flex-wrap gap-0.5">@foreach (array_slice((array) $v->tokens, 0, 12) as $k => $hex)<span class="inline-block size-4 rounded border border-panel-border" style="background: {{ $hex }}" title="{{ $k }} {{ $hex }}"></span>@endforeach</div></td>
                            <td data-label="{{ $t('cols.when') }}" class="text-xs" dir="ltr">{{ $v->activated_at?->format('Y-m-d H:i') }}</td>
                            <td class="ui-table-actions">@can('ai_theme.approve')@unless ($v->is_active)<button type="button" class="btn-sm btn-secondary" data-sugg-post="{{ route('marketer.suggestions.themes.activate', $v->id) }}" data-confirm="{{ $t('confirm.activate') }}">{{ $t('btn.activate') }}</button>@endunless @endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-2 text-panel-text-muted">{{ $t('themes.none') }}</td></tr>
                    @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        @endif

        <div>
            <form id="sugg-filters" data-base="{{ $dtUrl }}">
                <x-ui.table-toolbar id="sugg-filter-sheet">
                    <div class="grid w-full grid-cols-1 items-end gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        <x-ui.select name="status" :label="$t('filters.status')" :options="['' => $t('filters.all')] + collect($statuses)->mapWithKeys(fn ($st) => [$st->value => $st->label()])->all()" />
                        @if (count($pageTypes) > 1)
                            <x-ui.select name="type" :label="$t('filters.type')" :options="['' => $t('filters.all')] + collect($pageTypes)->mapWithKeys(fn ($ty) => [$ty->value => $ty->label()])->all()" />
                        @endif
                        @if ($page === 'seo')
                            <x-ui.select name="target" :label="$t('filters.target')" :options="['' => $t('filters.all')] + collect($seoTargets)->mapWithKeys(fn ($tg) => [$tg => $t('targets.'.$tg)])->all()" />
                        @endif
                        <x-ui.select name="priority" :label="$t('filters.priority')" :options="['' => $t('filters.all')] + collect(\App\Enums\Marketing\SuggestionPriority::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all()" />
                        <x-ui.date name="from" :label="$t('filters.from')" />
                        <x-ui.date name="to" :label="$t('filters.to')" />
                        <x-ui.search name="q" :label="$t('filters.search')" maxlength="100" :placeholder="''" />
                        <x-ui.input name="product_id" type="number" min="1" :label="$t('filters.product')" dir="ltr" />
                        <x-ui.select name="category_id" :label="$t('filters.category')" :options="['' => $t('filters.all')] + \App\Models\Category::query()->orderBy('path')->limit(300)->get(['id', 'name', 'depth'])->mapWithKeys(fn ($c) => [$c->id => str_repeat('— ', (int) $c->depth).$c->name])->all()" />
                        <x-ui.select name="sort" :label="$t('filters.sort')" :options="collect(['newest', 'oldest', 'priority', 'updated'])->mapWithKeys(fn ($so) => [$so => $t('sorts.'.$so)])->all()" />
                        <div class="flex items-end gap-2">
                            <x-ui.button type="submit">{{ $t('filters.apply') }}</x-ui.button>
                            <button type="reset" class="btn btn-secondary btn-md">{{ $t('filters.reset') }}</button>
                        </div>
                    </div>
                </x-ui.table-toolbar>
            </form>
            <x-ui.data-table :table="$table" :url="$dtUrl" id="sugg-table" />
        </div>
    </div>
    @include('marketer.suggestions._script')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const f = document.getElementById('sugg-filters');
        const reload = () => {
            const base = f.dataset.base;
            const qs = new URLSearchParams([...new FormData(f)].filter(([, v]) => v !== '')).toString();
            window.jQuery('#sugg-table').DataTable().ajax.url(base + (base.includes('?') ? '&' : '?') + qs).load();
        };
        f.addEventListener('submit', (e) => { e.preventDefault(); reload(); });
        f.addEventListener('reset', () => setTimeout(reload, 0));
    });
    </script>
</x-layouts.marketer>
