<x-layouts.storefront :title="__('store.bundles')" :breadcrumbs="[[__('store.breadcrumb_home'), route('storefront.home')], [__('store.bundles'), url()->current()]]">
    {{-- design 27:8602 / 27:8853: gold header, 2 / 3 / 4-col bundle cards --}}
    <x-breadcrumbs :items="[[__('store.breadcrumb_home'), route('storefront.home')], [__('store.bundles'), url()->current()]]" />
    <header class="relative mb-6 overflow-hidden rounded-3xl bg-accent px-5 py-7 text-slate-950 lg:mb-8 lg:rounded-[32px] lg:px-10 lg:py-10">
        <span class="pointer-events-none absolute -bottom-20 -end-12 size-40 rounded-full bg-sun-300 lg:size-60" aria-hidden="true"></span>
        <span class="pointer-events-none absolute -top-5 end-32 size-12 rounded-full border-[6px] border-sun-300 max-sm:hidden" aria-hidden="true"></span>
        <h1 class="page-title relative">{{ __('store.bundles') }}</h1>
        <p class="relative mt-1 max-w-xl text-sm/[22px] font-semibold">{{ __('store.bundles_intro') }}</p>
    </header>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4 lg:gap-5">
        @forelse ($bundles as $b)@include('storefront._bundle-card', ['b' => $b])@empty<x-empty-state type="gift" :title="__('store.bundles_empty')" :href="route('storefront.home')" :action="__('store.continue_shopping')" />@endforelse
    </div>
</x-layouts.storefront>
