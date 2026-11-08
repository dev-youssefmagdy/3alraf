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
    <div class="space-y-4" data-suggestions-page="{{ $page }}">
        <div>
            <h1 class="text-xl font-bold text-slate-800">{{ $title }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $t('overview_hint') }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" data-kpis>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" data-kpi="total">
                <p class="text-sm text-slate-500">{{ $t('kpi.total') }}</p>
                <p class="mt-1 text-2xl font-bold text-brand-600">{{ number_format($counts['total']) }}</p>
            </div>
            @foreach (['pending', 'approved', 'rejected', 'applied', 'failed'] as $st)
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" data-kpi="{{ $st }}">
                    <p class="text-sm text-slate-500">{{ $t('statuses.'.$st) }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $st === 'failed' || $st === 'rejected' ? 'text-red-600' : ($st === 'applied' || $st === 'approved' ? 'text-emerald-700' : 'text-slate-700') }}">{{ number_format($counts['by_status'][$st] ?? 0) }}</p>
                </div>
            @endforeach
        </div>

        @if ($page === 'overview')
            <div class="grid gap-4 lg:grid-cols-3">
                <x-card :title="$t('kpi.by_type')" class="lg:col-span-1">
                    <ul class="divide-y divide-slate-100 text-sm" data-by-type>
                        @foreach (\App\Enums\Marketing\SuggestionTypeKey::cases() as $type)
                            <li class="flex items-center justify-between py-1.5">
                                <a href="{{ route('marketer.suggestions.'.$type->page()) }}" class="text-brand-700 hover:underline">{{ $type->label() }}</a>
                                <span class="font-semibold text-slate-700">{{ $counts['by_type'][$type->value] ?? 0 }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
                <x-card :title="$t('kpi.recent')" class="lg:col-span-2">
                    @forelse ($recent as $a)
                        <div class="flex flex-wrap items-center gap-x-2 border-b border-slate-100 py-1.5 text-sm last:border-0">
                            <span class="text-xs text-slate-400" dir="ltr">{{ $a->created_at?->format('Y-m-d H:i') }}</span>
                            <span class="badge badge-muted">{{ $t('actions.'.$a->action) }}</span>
                            @if ($a->suggestion)<a class="min-w-0 truncate text-brand-700 hover:underline" href="{{ route('marketer.suggestions.show', $a->suggestion_id) }}">#{{ $a->suggestion_id }} {{ $a->suggestion->title }}</a>@endif
                            <span class="text-xs text-slate-500">{{ $a->actorLabel() }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">{{ __('admin.no_data') }}</p>
                    @endforelse
                </x-card>
            </div>
        @endif

        @if ($page === 'themes')
            <x-card :title="$t('themes.versions')">
                <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-slate-500">{{ $t('themes.active') }}:</span>
                    <b data-active-theme>{{ $activeTheme ? $activeTheme->name.' · '.$activeTheme->key.' v'.$activeTheme->version : $t('themes.none') }}</b>
                    @can('ai_theme.approve')
                        @if ($activeTheme)
                            <button type="button" class="btn-sm btn-secondary" data-sugg-post="{{ route('marketer.suggestions.themes.revert') }}" data-confirm="{{ $t('confirm.revert') }}">{{ $t('btn.revert') }}</button>
                            <button type="button" class="btn-sm btn-danger" data-sugg-post="{{ route('marketer.suggestions.themes.off') }}" data-confirm="{{ $t('confirm.defaults') }}">{{ $t('btn.defaults') }}</button>
                        @endif
                    @endcan
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-start text-slate-500"><th class="py-1 text-start">{{ $t('fields.name') }}</th><th class="text-start">{{ $t('themes.version') }}</th><th class="text-start">{{ $t('themes.swatches') }}</th><th class="text-start">{{ $t('cols.when') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($themeVersions as $v)
                            <tr class="border-t border-slate-100" data-theme-version="{{ $v->id }}">
                                <td class="py-1.5">{{ $v->name }} <span class="text-xs text-slate-400" dir="ltr">{{ $v->key }}</span> @if ($v->is_active)<span class="badge badge-success">{{ $t('themes.active') }}</span>@endif</td>
                                <td>v{{ $v->version }}</td>
                                <td><div class="flex flex-wrap gap-0.5">@foreach (array_slice((array) $v->tokens, 0, 12) as $k => $hex)<span class="inline-block size-4 rounded border border-slate-200" style="background: {{ $hex }}" title="{{ $k }} {{ $hex }}"></span>@endforeach</div></td>
                                <td class="text-xs" dir="ltr">{{ $v->activated_at?->format('Y-m-d H:i') }}</td>
                                <td>@can('ai_theme.approve')@unless ($v->is_active)<button type="button" class="btn-sm btn-secondary" data-sugg-post="{{ route('marketer.suggestions.themes.activate', $v->id) }}" data-confirm="{{ $t('confirm.activate') }}">{{ $t('btn.activate') }}</button>@endunless @endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-2 text-slate-500">{{ $t('themes.none') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif

        <x-card>
            <form id="sugg-filters" class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6" data-base="{{ $dtUrl }}">
                <div><label class="form-label" for="f-status">{{ $t('filters.status') }}</label>
                    <select id="f-status" name="status" class="form-control"><option value="">{{ $t('filters.all') }}</option>@foreach ($statuses as $st)<option value="{{ $st->value }}">{{ $st->label() }}</option>@endforeach</select></div>
                @if (count($pageTypes) > 1)
                    <div><label class="form-label" for="f-type">{{ $t('filters.type') }}</label>
                        <select id="f-type" name="type" class="form-control"><option value="">{{ $t('filters.all') }}</option>@foreach ($pageTypes as $ty)<option value="{{ $ty->value }}">{{ $ty->label() }}</option>@endforeach</select></div>
                @endif
                @if ($page === 'seo')
                    <div><label class="form-label" for="f-target">{{ $t('filters.target') }}</label>
                        <select id="f-target" name="target" class="form-control"><option value="">{{ $t('filters.all') }}</option>@foreach ($seoTargets as $tg)<option value="{{ $tg }}">{{ $t('targets.'.$tg) }}</option>@endforeach</select></div>
                @endif
                <div><label class="form-label" for="f-priority">{{ $t('filters.priority') }}</label>
                    <select id="f-priority" name="priority" class="form-control"><option value="">{{ $t('filters.all') }}</option>@foreach (\App\Enums\Marketing\SuggestionPriority::cases() as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select></div>
                <div><label class="form-label" for="f-from">{{ $t('filters.from') }}</label><input id="f-from" type="date" name="from" class="form-control"></div>
                <div><label class="form-label" for="f-to">{{ $t('filters.to') }}</label><input id="f-to" type="date" name="to" class="form-control"></div>
                <div><label class="form-label" for="f-q">{{ $t('filters.search') }}</label><input id="f-q" type="search" name="q" class="form-control" maxlength="100"></div>
                <div><label class="form-label" for="f-product">{{ $t('filters.product') }}</label><input id="f-product" type="number" min="1" name="product_id" class="form-control" dir="ltr"></div>
                <div><label class="form-label" for="f-category">{{ $t('filters.category') }}</label>
                    <select id="f-category" name="category_id" class="form-control"><option value="">{{ $t('filters.all') }}</option>@foreach (\App\Models\Category::query()->orderBy('path')->limit(300)->get(['id', 'name', 'depth']) as $c)<option value="{{ $c->id }}">{{ str_repeat('— ', (int) $c->depth) }}{{ $c->name }}</option>@endforeach</select></div>
                <div><label class="form-label" for="f-sort">{{ $t('filters.sort') }}</label>
                    <select id="f-sort" name="sort" class="form-control">@foreach (['newest', 'oldest', 'priority', 'updated'] as $so)<option value="{{ $so }}">{{ $t('sorts.'.$so) }}</option>@endforeach</select></div>
                <div class="col-span-2 flex items-end gap-2 sm:col-span-1">
                    <button type="submit" class="btn btn-primary">{{ $t('filters.apply') }}</button>
                    <button type="reset" class="btn btn-secondary">{{ $t('filters.reset') }}</button>
                </div>
            </form>
            <x-datatable :table="$table" :url="$dtUrl" id="sugg-table" />
        </x-card>
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
