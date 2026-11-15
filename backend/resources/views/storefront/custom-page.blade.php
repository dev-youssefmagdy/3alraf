@php($bc = [[__('store.breadcrumb_home'), route('storefront.home')], [$page->title, url()->current()]])
<x-layouts.storefront :title="$page->title" :description="$page->title.' - '.setting('name').'. '.__('store.seo_default_desc')" :breadcrumbs="$bc">
    {{-- Custom page (design 28:9674 / 28:10039, lower part): pop H1 → GET filter form in a pop-soft panel (price min / max, sort, Apply) → product grid 2 / 3 / 4 / 5 → pagination. --}}
    <x-breadcrumbs :items="$bc" />
    <h1 class="page-title !text-pop-600 [overflow-wrap:anywhere]">{{ $page->title }}</h1>
    <form method="GET" class="mt-4 grid gap-3 rounded-3xl bg-pop-50 p-4 sm:grid-cols-2 lg:mt-5 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end lg:px-5 lg:py-4">
        <x-input class="space-y-1" name="price_min" type="number" step="0.01" min="0" inputmode="decimal" :label="__('growth.fields.price_min')" :value="request('price_min')" />
        <x-input class="space-y-1" name="price_max" type="number" step="0.01" min="0" inputmode="decimal" :label="__('growth.fields.price_max')" :value="request('price_max')" />
        <x-select class="space-y-1 sm:col-span-2 lg:col-span-1" name="sort" :label="__('growth.fields.sort')" :options="collect($sorts)->mapWithKeys(fn ($s) => [$s => __('growth.sort.'.$s)])->all()" :value="request('sort', $page->filters['sort'] ?? 'newest')" />
        <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
        <button class="btn btn-pop w-full sm:col-span-2 lg:col-span-1 lg:w-auto lg:min-h-12 lg:px-6">{{ __('growth.apply') }}</button>
    </form>
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:mt-6 lg:grid-cols-4 lg:gap-5 xl:grid-cols-5">
        @forelse ($items as $item)<x-product-card :product="$item" :eager="$loop->index < 4" />@empty<x-empty-state type="search" :title="__('store.no_results')" :text="__('store.no_results_text')" />@endforelse
    </div>
    <div class="mt-8">{{ $items->links('storefront._pagination') }}</div>
</x-layouts.storefront>
