<x-layouts.storefront :title="__('store.all_brands')">
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold">{{ __('store.all_brands') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $brands->count() }} {{ __('store.brands') }}</p>
    </div>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
        @forelse ($brands as $br)
            <a href="{{ route('storefront.brand.show', $br->slug) }}" class="group flex flex-col overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200 transition hover:-translate-y-1 hover:shadow-lg hover:ring-brand-500">
                <span class="grid aspect-[4/3] place-items-center bg-slate-50 p-6">
                    @if ($br->logo_url)
                        <img src="{{ $br->logo_url }}" alt="{{ $br->name }}" class="max-h-full max-w-full object-contain transition duration-300 group-hover:scale-110" loading="lazy">
                    @else
                        <span class="grid size-20 place-items-center rounded-full bg-brand-50 text-3xl font-extrabold text-brand-600">{{ mb_strtoupper(mb_substr($br->name, 0, 1)) }}</span>
                    @endif
                </span>
                <span class="border-t border-slate-100 px-3 py-3 text-center">
                    <span class="block truncate text-sm font-bold text-slate-800 group-hover:text-brand-600">{{ $br->name }}</span>
                    <span class="mt-0.5 block text-xs text-slate-500">{{ $br->sellable_count }} {{ __('store.brand_products') }}</span>
                </span>
            </a>
        @empty
            <p class="col-span-full text-slate-500">{{ __('store.brands_empty') }}</p>
        @endforelse
    </div>
</x-layouts.storefront>
