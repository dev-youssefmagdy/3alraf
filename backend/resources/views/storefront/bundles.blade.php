<x-layouts.storefront :title="__('store.bundles')">
    <h1 class="mb-4 text-2xl font-extrabold">{{ __('store.bundles') }}</h1>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @forelse ($bundles as $b)@include('storefront._bundle-card', ['b' => $b])@empty<p class="col-span-full text-slate-500">{{ __('store.bundles_empty') }}</p>@endforelse
    </div>
</x-layouts.storefront>
