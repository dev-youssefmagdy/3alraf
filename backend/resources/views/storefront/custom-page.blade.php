<x-layouts.storefront :title="$page->title">
    <h1 class="mb-4 text-2xl font-bold">{{ $page->title }}</h1>
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <x-input class="w-32" name="price_min" type="number" step="0.01" min="0" :label="__('growth.fields.price_min')" :value="request('price_min')" />
        <x-input class="w-32" name="price_max" type="number" step="0.01" min="0" :label="__('growth.fields.price_max')" :value="request('price_max')" />
        <x-select class="w-48" name="sort" :label="__('growth.fields.sort')" :options="collect($sorts)->mapWithKeys(fn ($s) => [$s => __('growth.sort.'.$s)])->all()" :value="request('sort', $page->filters['sort'] ?? 'newest')" />
        <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
        <button class="btn btn-secondary">{{ __('growth.apply') }}</button>
    </form>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @forelse ($items as $tp)@include('storefront._product-card', ['tp' => $tp])@empty<p class="col-span-full text-slate-500">{{ __('admin.no_data') }}</p>@endforelse
    </div>
    <div class="mt-6">{{ $items->links() }}</div>
</x-layouts.storefront>
