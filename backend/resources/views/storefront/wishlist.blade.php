<x-layouts.storefront :title="__('growth.wishlist')">
    <h1 class="mb-4 text-xl font-bold">{{ __('growth.wishlist') }}</h1>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @forelse ($items as $tp)@include('storefront._product-card', ['tp' => $tp])@empty<p class="col-span-full text-slate-500">{{ __('growth.wishlist_empty') }}</p>@endforelse
    </div>
</x-layouts.storefront>
