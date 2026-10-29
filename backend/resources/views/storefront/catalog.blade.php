@php
    $listing = $listing ?? null; // brand / tag landing page: ['title','action','image','description']
    $isSearch = $category === null && ! $listing;
    $pageTitle = $listing ? $listing['title'] : ($isSearch ? ($filter->q ? __('store.search_results', ['q' => $filter->q]) : __('store.search')) : $category->name);
    $action = $listing ? $listing['action'] : ($isSearch ? route('storefront.search') : route('storefront.category.show', $category->slug));
    $selectedValues = $filter->values;
    $activeCount = count($filter->brands) + count($filter->tags) + count($filter->values) + ($filter->priceMin !== null ? 1 : 0) + ($filter->priceMax !== null ? 1 : 0);
    $bc = array_merge([[__('store.breadcrumb_home'), route('storefront.home')]], collect($crumbs ?? [])->map(fn ($c) => [$c->name, route('storefront.category.show', $c->slug)])->all(), ($isSearch || $listing) ? [[$pageTitle, url()->current()]] : []);
@endphp
<x-layouts.storefront :title="$category ? ($category->name) : $pageTitle" :description="$category?->description ?? ($listing['description'] ?? null)" :image="$category?->image_url ?? ($listing['image'] ?? null)" :noindex="$isSearch" :breadcrumbs="$bc">
    <nav class="mb-4 flex flex-wrap items-center gap-1 text-sm text-slate-500" aria-label="breadcrumb">
        <a href="{{ route('storefront.home') }}" class="hover:text-brand-600">{{ __('store.breadcrumb_home') }}</a>
        @foreach ($crumbs as $c)<span>/</span>@if ($loop->last)<span class="font-semibold text-slate-800">{{ $c->name }}</span>@else<a href="{{ route('storefront.category.show', $c->slug) }}" class="hover:text-brand-600">{{ $c->name }}</a>@endif @endforeach
        @if ($isSearch || $listing)<span>/</span><span class="font-semibold text-slate-800">{{ $pageTitle }}</span>@endif
    </nav>

    @if ($children->isNotEmpty())
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ($children as $ch)<a href="{{ route('storefront.category.show', $ch->slug) }}" class="rounded-full border border-slate-200 bg-white px-4 py-1.5 text-sm hover:border-brand-500 hover:text-brand-600">{{ $ch->name }}</a>@endforeach
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[16rem_1fr]" data-catalog data-action="{{ $action }}">
        <aside class="lg:block" data-filters-panel>
            <button type="button" class="btn btn-secondary mb-3 w-full lg:hidden" data-filters-toggle>{{ __('store.filters') }} @if ($activeCount)({{ $activeCount }})@endif</button>
            <form method="GET" action="{{ $action }}" class="hidden space-y-5 rounded-xl border border-slate-200 bg-white p-4 lg:block" data-filter-form>
                @if ($isSearch)<input type="hidden" name="q" value="{{ $filter->q }}">@endif
                <input type="hidden" name="sort" value="{{ $filter->sort }}" data-sort-input>
                @if ($facets['brands']->isNotEmpty())
                    <fieldset><legend class="mb-2 font-bold">{{ __('store.brand') }}</legend>
                        <div class="max-h-48 space-y-1 overflow-y-auto text-sm">
                            @foreach ($facets['brands'] as $b)
                                <label class="flex cursor-pointer items-center gap-2"><input type="checkbox" name="brands[]" value="{{ $b->id }}" @checked(in_array($b->id, $filter->brands)) class="accent-brand-600"> {{ $b->name }}</label>
                            @endforeach
                        </div></fieldset>
                @endif
                @if ($facets['tags']->isNotEmpty())
                    <fieldset><legend class="mb-2 font-bold">{{ __('store.tags') }}</legend>
                        <div class="flex flex-wrap gap-2 text-sm">
                            @foreach ($facets['tags'] as $tg)
                                <label class="cursor-pointer"><input type="checkbox" name="tags[]" value="{{ $tg->id }}" @checked(in_array($tg->id, $filter->tags)) class="peer sr-only">
                                    <span class="inline-flex rounded-full border border-slate-300 px-3 py-1 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700">{{ $tg->name }}</span></label>
                            @endforeach
                        </div></fieldset>
                @endif
                @foreach ($facets['attributes'] as $attr)
                    <fieldset><legend class="mb-2 font-bold">{{ $attr->name }}</legend>
                        <div class="flex flex-wrap gap-2 text-sm">
                            @foreach ($attr->values as $v)
                                <label class="cursor-pointer"><input type="checkbox" name="values[]" value="{{ $v->id }}" @checked(in_array($v->id, $selectedValues)) class="peer sr-only">
                                    <span class="inline-flex items-center gap-1 rounded-full border border-slate-300 px-3 py-1 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700">
                                        @if ($v->color_hex)<i class="size-3 rounded-full border border-slate-300" style="background: {{ $v->color_hex }}"></i>@endif{{ $v->value }}</span></label>
                            @endforeach
                        </div></fieldset>
                @endforeach
                <fieldset><legend class="mb-2 font-bold">{{ __('store.price') }} <small class="font-normal text-slate-400">({{ $facets['price']['min'] }} - {{ $facets['price']['max'] }})</small></legend>
                    <div class="flex items-center gap-2">
                        <input type="number" min="0" step="1" name="price_min" value="{{ $filter->priceMin }}" placeholder="{{ __('store.price_from') }}" class="form-control !px-2">
                        <input type="number" min="0" step="1" name="price_max" value="{{ $filter->priceMax }}" placeholder="{{ __('store.price_to') }}" class="form-control !px-2">
                    </div></fieldset>
                <div class="flex gap-2">
                    <button class="btn btn-primary flex-1">{{ __('store.apply') }}</button>
                    @if ($activeCount)<a href="{{ $action }}{{ $isSearch && $filter->q ? '?q='.urlencode($filter->q) : '' }}" class="btn btn-secondary" data-filter-reset>{{ __('store.reset') }}</a>@endif
                </div>
            </form>
        </aside>

        <section>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-3 ring-1 ring-slate-100">
                <div><h1 class="text-lg font-extrabold">{{ $pageTitle }}</h1><p class="text-xs text-slate-500" data-count>{{ __('store.results_count', ['n' => $items->total()]) }}</p></div>
                <label class="flex items-center gap-2 text-sm">{{ __('store.sort') }}
                    <select class="form-control !w-auto !py-1.5" data-sort>
                        @foreach (\App\DTOs\Storefront\CatalogFilter::SORTS as $s)<option value="{{ $s }}" @selected($filter->sort === $s)>{{ __('store.sorts.'.$s) }}</option>@endforeach
                    </select></label>
            </div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4" data-grid>@include('storefront._grid', ['items' => $items])</div>
            <div class="mt-6" data-pagination>{{ $items->links('storefront._pagination') }}</div>
        </section>
    </div>
    @if ($isSearch && $filter->q)<script>window.sfTrack && sfTrack('search', {search_term: @js($filter->q), items: []});</script>@endif
</x-layouts.storefront>
