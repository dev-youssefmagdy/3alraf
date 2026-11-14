<x-layouts.storefront :title="__('growth.wishlist')" :noindex="true">
    {{-- design 26:7667 / 26:7963 (empty: 32:2994): H1 + count, 2 / 3 / 4 / 5-col grid --}}
    <div class="mb-5 flex flex-wrap items-baseline gap-x-3 gap-y-1 lg:mb-8">
        <h1 class="page-title">{{ __('growth.wishlist') }}</h1>
        @if (count($items))<p class="text-sm font-semibold text-slate-600">{{ __('store.results_count', ['n' => count($items)]) }}</p>@endif
    </div>
    @if (count($items))
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4 lg:gap-5 xl:grid-cols-5">
            @foreach ($items as $item)<x-product-card :product="$item" :eager="$loop->index < 4" />@endforeach
        </div>
    @else
        <x-empty-state type="wishlist" :title="__('growth.wishlist_empty')" :text="__('store.wishlist_empty_text')" :href="route('storefront.home')" :action="__('store.continue_shopping')" class="panel !py-12" />
    @endif
</x-layouts.storefront>
