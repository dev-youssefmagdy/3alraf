<x-layouts.admin :title="__('admin.products')">
    <x-slot:header>
        <x-ui.page-header :title="__('admin.products')" :count="number_format(\App\Models\Product::query()->count())">
            {{-- Search by image (moved next to the table search box by image-search.js; paste / drop anywhere on this page also opens it) --}}
            <button type="button" data-is-open class="inline-flex size-10 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-950 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"
                    title="{{ __('admin.image_search.tooltip') }}" aria-label="{{ __('admin.image_search.title') }}" aria-haspopup="dialog" aria-controls="image-search">
                <svg class="size-5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.66667 2.66667H6.33333L4.66667 4.66667H2.66667C2.31304 4.66667 1.97391 4.80714 1.72386 5.05719C1.47381 5.30724 1.33333 5.64638 1.33333 6V12C1.33333 12.3536 1.47381 12.6928 1.72386 12.9428C1.97391 13.1929 2.31304 13.3333 2.66667 13.3333H13.3333C13.687 13.3333 14.0261 13.1929 14.2761 12.9428C14.5262 12.6928 14.6667 12.3536 14.6667 12V6C14.6667 5.64638 14.5262 5.30724 14.2761 5.05719C14.0261 4.80714 13.687 4.66667 13.3333 4.66667H11.3333L9.66667 2.66667Z"/><path d="M10 8.66667C10 9.77124 9.10457 10.6667 8 10.6667C6.89543 10.6667 6 9.77124 6 8.66667C6 7.5621 6.89543 6.66667 8 6.66667C9.10457 6.66667 10 7.5621 10 8.66667Z"/></svg>
            </button>
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
    @include('admin.products._image-search')
</x-layouts.admin>
