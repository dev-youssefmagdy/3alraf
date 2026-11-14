{{-- Blog index (design 28:10444 / 28:10691): mint header (H1 + intro) → post cards, 1 / 2 / 3 columns → pagination. --}}
<x-layouts.storefront :title="__('growth.blog')">
    <header class="relative isolate mb-6 overflow-hidden rounded-3xl bg-mint-400 p-5 text-slate-950 lg:mb-8 lg:rounded-[32px] lg:px-14 lg:py-8">
        <span class="pointer-events-none absolute -end-10 -top-12 -z-10 size-40 rounded-full bg-mint-100/50 lg:size-56" aria-hidden="true"></span>
        <h1 class="page-title">{{ __('growth.blog') }}</h1>
        <p class="mt-1.5 text-base/[26px]">{{ __('store.blog_intro') }}</p>
    </header>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
        @forelse ($posts as $p)
            @include('storefront.blog._card', ['post' => $p, 'heading' => 'h2'])
        @empty
            <x-empty-state type="search" :title="__('admin.no_data')" :href="route('storefront.home')" :action="__('store.continue_shopping')" />
        @endforelse
    </div>
    <div class="mt-8">{{ $posts->links('storefront._pagination') }}</div>
</x-layouts.storefront>
