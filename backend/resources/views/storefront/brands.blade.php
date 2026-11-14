<x-layouts.storefront :title="__('store.all_brands')" :breadcrumbs="[[__('store.breadcrumb_home'), route('storefront.home')], [__('store.all_brands'), url()->current()]]">
    {{-- design 27:8105 / 27:8337: sun header, 2 / 3 / 4 / 6-col brand tiles --}}
    <x-breadcrumbs :items="[[__('store.breadcrumb_home'), route('storefront.home')], [__('store.all_brands'), url()->current()]]" />
    <header class="relative mb-6 overflow-hidden rounded-3xl bg-sun-400 px-5 py-7 text-slate-950 lg:mb-8 lg:rounded-[32px] lg:px-10 lg:py-10">
        <span class="pointer-events-none absolute -bottom-16 -end-10 size-36 rounded-full bg-pop-600 lg:size-56" aria-hidden="true"></span>
        <span class="pointer-events-none absolute -top-5 end-28 size-12 rounded-full border-[6px] border-white/70 max-sm:hidden" aria-hidden="true"></span>
        <h1 class="page-title relative">{{ __('store.all_brands') }}</h1>
        <p class="relative mt-1 text-sm font-bold">{{ __('store.brands_count', ['n' => $brands->count()]) }}</p>
    </header>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 lg:gap-5">
        @forelse ($brands as $br)
            @include('storefront._brand-tile', ['br' => $br, 'i' => $loop->index])
        @empty
            <x-empty-state type="search" :title="__('store.brands_empty')" :href="route('storefront.home')" :action="__('store.continue_shopping')" />
        @endforelse
    </div>
</x-layouts.storefront>
