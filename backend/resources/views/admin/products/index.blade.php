<x-layouts.admin :title="__('admin.products')">
    <x-slot:header>
        <x-ui.page-header :title="__('admin.products')" :count="number_format(\App\Models\Product::query()->count())">
            @can('products.create')<x-button :href="route('admin.products.create')">+ {{ __('admin.add_new') }}</x-button>@endcan
        </x-ui.page-header>
    </x-slot:header>
    @php($drafts = request()->boolean('drafts'))
    @php($draftCount = \App\Models\Product::query()->drafts()->count())
    <x-card>
        @if ($draftCount || $drafts)
            <div class="mb-4 flex gap-2 text-sm">
                <a href="{{ route('admin.products.index') }}" class="btn-sm {{ $drafts ? 'btn-secondary' : 'btn-primary' }}">{{ __('admin.drafts.all') }}</a>
                <a href="{{ route('admin.products.index', ['drafts' => 1]) }}" class="btn-sm {{ $drafts ? 'btn-primary' : 'btn-secondary' }}">{{ __('admin.drafts.title') }} ({{ number_format($draftCount) }})</a>
            </div>
        @endif
        <x-datatable :table="$table" :url="route('admin.products.datatable', $drafts ? ['drafts' => 1] : [])" id="products-table" />
    </x-card>
</x-layouts.admin>
