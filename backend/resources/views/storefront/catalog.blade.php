@php
    $listing = $listing ?? null; // brand / tag landing page: ['title','action','image','description']
    $isSearch = $category === null && ! $listing;
    $pageTitle = $listing ? $listing['title'] : ($isSearch ? ($filter->q ? __('store.search_results', ['q' => $filter->q]) : __('store.search')) : $category->name);
    $action = $listing ? $listing['action'] : ($isSearch ? route('storefront.search') : route('storefront.category.show', $category->slug));
    $selectedValues = $filter->values;
    $activeCount = count($filter->brands) + count($filter->tags) + count($filter->values) + ($filter->priceMin !== null ? 1 : 0) + ($filter->priceMax !== null ? 1 : 0);
    $bc = array_merge([[__('store.breadcrumb_home'), route('storefront.home')]], collect($crumbs ?? [])->map(fn ($c) => [$c->name, route('storefront.category.show', $c->slug)])->all(), ($isSearch || $listing) ? [[$pageTitle, url()->current()]] : []);
    // header colour block: pop for categories, violet for search, brand for brand / tag landings (design 24:2876 / 24:3318)
    $tone = $isSearch ? 'bg-violet-500' : ($listing ? 'bg-brand-800' : 'bg-pop-600');
    $resetUrl = $action.($isSearch && $filter->q ? '?q='.urlencode($filter->q) : '');
    $check = '<svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>';
@endphp
<x-layouts.storefront :title="$category ? ($category->name) : $pageTitle" :description="$category?->description ?? ($listing['description'] ?? null)" :image="$category?->image_url ?? ($listing['image'] ?? null)" :noindex="$isSearch" :breadcrumbs="$bc">
    <x-breadcrumbs :items="$bc" />
    <div data-catalog data-action="{{ $action }}">

    {{-- category / search header: colour block with H1, count and sub-category chips --}}
    <header class="relative mb-5 overflow-hidden rounded-3xl {{ $tone }} px-5 py-6 text-white lg:mb-8 lg:rounded-[32px] lg:px-10 lg:py-9">
        <span class="pointer-events-none absolute -bottom-20 -end-10 size-48 rounded-full bg-white/10 lg:size-72" aria-hidden="true"></span>
        <span class="pointer-events-none absolute -top-6 end-24 size-14 rounded-full border-[6px] border-sun-400 max-sm:hidden" aria-hidden="true"></span>
        <div class="relative flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <h1 class="break-words font-display text-[28px]/9 font-extrabold lg:text-[44px]/[52px]">{{ $pageTitle }}</h1>
            <p class="text-sm font-bold text-white/90" data-count>{{ __('store.results_count', ['n' => $items->total()]) }}</p>
        </div>
        @if (! empty($listing['description']))<p class="relative mt-2 max-w-2xl text-sm/[22px] text-white/90">{{ $listing['description'] }}</p>@endif
        @if ($children->isNotEmpty())
            <nav data-swiper="chips" data-swiper-options='{"breakpoints":{"1024":{"enabled":false}}}' class="relative mt-4 lg:mt-5" aria-label="{{ __('store.sub_categories') }}">
                <div class="swiper max-lg:!overflow-visible">
                    <div class="swiper-wrapper lg:!flex-wrap lg:!gap-y-2">
                        @foreach ($children as $ch)<div class="swiper-slide !w-auto"><a href="{{ route('storefront.category.show', $ch->slug) }}" class="chip !border-white !text-slate-950 hover:!bg-sun-100">{{ $ch->name }}</a></div>@endforeach
                    </div>
                </div>
            </nav>
        @endif
    </header>

    <div class="grid gap-6 lg:grid-cols-[296px_1fr] lg:gap-8">
        {{-- filters: sidebar ≥1024, bottom sheet below (design 32:2442) --}}
        <aside data-filters-panel class="lg:self-start">
            <div data-filters-scrim class="fixed inset-0 z-40 hidden bg-slate-950/50 lg:!hidden" aria-hidden="true"></div>
            <form method="GET" action="{{ $action }}" data-filter-form id="catalog-filters" aria-labelledby="filters-title"
                class="hidden max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-50 max-lg:max-h-[88vh] max-lg:overflow-y-auto max-lg:overscroll-contain max-lg:rounded-t-3xl max-lg:pb-[calc(1rem+env(safe-area-inset-bottom))] max-lg:shadow-overlay rounded-3xl border border-slate-200 bg-white p-5 lg:sticky lg:top-40 lg:block">
                <span class="mx-auto mb-3 block h-1.5 w-10 rounded-full bg-slate-200 lg:hidden" aria-hidden="true"></span>
                <div class="mb-4 flex items-center justify-between gap-2">
                    <h2 id="filters-title" class="flex items-center gap-2 font-display text-[22px]/[30px] font-bold text-slate-950">
                        <svg class="size-5 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>{{ __('store.filters') }}</h2>
                    <button type="button" class="icon-btn icon-btn-plain lg:hidden" data-filters-toggle aria-controls="catalog-filters" aria-label="{{ __('store.close') }}"><svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                </div>
                @if ($isSearch)<input type="hidden" name="q" value="{{ $filter->q }}">@endif
                <input type="hidden" name="sort" value="{{ $filter->sort }}" data-sort-input>
                <div class="divide-y divide-slate-100">
                    @if ($facets['brands']->isNotEmpty())
                        <details class="group py-3 first:pt-0" open>
                            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between font-bold text-slate-950 [&::-webkit-details-marker]:hidden">{{ __('store.brand') }}<span class="text-xl leading-none text-brand-600 group-open:hidden" aria-hidden="true">+</span><span class="hidden text-xl leading-none text-brand-600 group-open:inline" aria-hidden="true">&minus;</span></summary>
                            <div class="max-h-56 space-y-0.5 overflow-y-auto pt-1 text-sm">
                                @foreach ($facets['brands'] as $b)
                                    <label class="flex min-h-11 cursor-pointer items-center gap-3"><input type="checkbox" name="brands[]" value="{{ $b->id }}" @checked(in_array($b->id, $filter->brands)) data-label="{{ $b->name }}" class="size-[22px] shrink-0 rounded-md border-slate-300 accent-brand-600"> {{ $b->name }}</label>
                                @endforeach
                            </div>
                        </details>
                    @endif
                    @if ($facets['tags']->isNotEmpty())
                        <fieldset class="pb-4"><legend class="mb-2 pt-4 font-bold text-slate-950">{{ __('store.tags') }}</legend>
                            <div class="flex flex-wrap gap-2 text-sm">
                                @foreach ($facets['tags'] as $tg)
                                    <label class="cursor-pointer"><input type="checkbox" name="tags[]" value="{{ $tg->id }}" @checked(in_array($tg->id, $filter->tags)) data-label="{{ $tg->name }}" class="peer sr-only">
                                        <span class="chip peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:ring-[3px] peer-focus-visible:ring-pop-500/45 [&>svg]:hidden peer-checked:[&>svg]:block">{!! $check !!}{{ $tg->name }}</span></label>
                                @endforeach
                            </div></fieldset>
                    @endif
                    @foreach ($facets['attributes'] as $attr)
                        <fieldset class="pb-4"><legend class="mb-2 pt-4 font-bold text-slate-950">{{ $attr->name }}</legend>
                            <div class="flex flex-wrap gap-2 text-sm">
                                @foreach ($attr->values as $v)
                                    <label class="cursor-pointer"><input type="checkbox" name="values[]" value="{{ $v->id }}" @checked(in_array($v->id, $selectedValues)) data-label="{{ $v->value }}" class="peer sr-only">
                                        <span class="chip peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:ring-[3px] peer-focus-visible:ring-pop-500/45 [&>svg]:hidden peer-checked:[&>svg]:block">{!! $check !!}
                                            @if ($v->color_hex)<i class="size-4 shrink-0 rounded-full border border-slate-300" style="background: {{ $v->color_hex }}"></i>@endif{{ $v->value }}</span></label>
                                @endforeach
                            </div></fieldset>
                    @endforeach
                    <fieldset class="pb-4"><legend class="mb-2 pt-4 font-bold text-slate-950">{{ __('store.price') }} <small class="font-normal text-slate-500" dir="ltr">({{ $facets['price']['min'] }} - {{ $facets['price']['max'] }})</small></legend>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" min="0" step="1" name="price_min" value="{{ $filter->priceMin }}" placeholder="{{ __('store.price_from') }}" aria-label="{{ __('store.price_from') }}" class="form-control w-full">
                            <input type="number" min="0" step="1" name="price_max" value="{{ $filter->priceMax }}" placeholder="{{ __('store.price_to') }}" aria-label="{{ __('store.price_to') }}" class="form-control w-full">
                        </div></fieldset>
                </div>
                <div class="flex items-center gap-2 border-t border-slate-100 pt-4 max-lg:sticky max-lg:-bottom-4 max-lg:bg-white">
                    <button class="btn btn-primary flex-1"><span class="lg:hidden">{{ __('store.show_results') }}</span><span class="max-lg:hidden">{{ __('store.apply') }}</span></button>
                    @if ($activeCount)<a href="{{ $resetUrl }}" class="btn btn-ghost" data-filter-reset>{{ __('store.reset') }}</a>@endif
                </div>
            </form>
        </aside>

        <section class="min-w-0" aria-labelledby="catalog-results">
            <h2 id="catalog-results" class="sr-only">{{ $pageTitle }}</h2>
            {{-- toolbar: filters button (phones), active chips, sort --}}
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <button type="button" class="btn btn-secondary lg:hidden" data-filters-toggle aria-controls="catalog-filters" aria-expanded="false">
                    <svg class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>
                    {{ __('store.filters') }}<span data-filters-count class="grid min-w-5 place-items-center rounded-full bg-pop-600 px-1.5 text-[11px] text-white {{ $activeCount ? '' : 'hidden' }}">{{ $activeCount }}</span>
                </button>
                <div data-active-filters class="order-last flex w-full flex-wrap gap-2 empty:hidden lg:order-none lg:w-auto lg:flex-1" aria-label="{{ __('store.active_filters') }}" role="group"></div>
                <label class="ms-auto flex items-center gap-2 text-sm font-semibold text-slate-600"><span class="max-sm:sr-only">{{ __('store.sort') }}</span>
                    <select class="form-control !min-h-11 !w-auto !rounded-full !py-2 font-bold !text-slate-950" data-sort aria-label="{{ __('store.sort') }}">
                        @foreach (\App\DTOs\Storefront\CatalogFilter::SORTS as $s)<option value="{{ $s }}" @selected($filter->sort === $s)>{{ __('store.sorts.'.$s) }}</option>@endforeach
                    </select></label>
            </div>
            <div class="grid grid-cols-2 gap-3 transition-opacity md:grid-cols-3 lg:gap-5 xl:grid-cols-4" data-grid>@include('storefront._grid', ['items' => $items])</div>
            <div class="mt-8" data-pagination>{{ $items->links('storefront._pagination') }}</div>
        </section>
    </div>
    </div>
    @if ($isSearch && $filter->q)<script>window.sfTrack && sfTrack('search', {search_term: @js($filter->q), items: []});</script>@endif
</x-layouts.storefront>
