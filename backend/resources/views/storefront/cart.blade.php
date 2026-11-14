<x-layouts.storefront :title="__('store.cart_title')" :noindex="true">
    {{-- design 25:5240 / 25:5569 (empty: 31:3 / 31:422): lines + sticky summary 420; lines, totals and the empty state are driven by storefront.js --}}
    <div data-cart-page>
        <div class="mb-5 flex flex-wrap items-baseline gap-x-3 gap-y-1 lg:mb-8">
            <h1 class="page-title">{{ __('store.cart_title') }}</h1>
            <p class="text-sm font-semibold text-slate-600" data-cart-head><span data-cart-count>0</span> {{ __('store.items_word') }}</p>
        </div>
        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_420px] lg:gap-8">
            <div class="min-w-0 space-y-3">
                <p class="hidden items-center gap-2 rounded-2xl bg-sun-100 px-4 py-3 text-sm font-semibold text-slate-950" data-cart-flash>
                    <svg class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2"/></svg>
                    <span>{{ __('store.flash_note') }}</span>
                </p>
                <div class="space-y-3" data-cart-lines aria-live="polite"></div>
                <a href="{{ route('storefront.home') }}" class="btn btn-ghost hidden !px-3" data-cart-continue>
                    <svg class="size-4 ltr:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    {{ __('store.continue_shopping') }}
                </a>
            </div>
            <aside class="panel space-y-4 lg:sticky lg:top-40 lg:p-6" data-cart-summary>
                <h2 class="section-title !text-[22px]/[30px]">{{ __('store.order_summary') }}</h2>
                @include('storefront._packaging')
                <dl class="space-y-2.5 text-sm" data-totals></dl>
                <a href="{{ route('storefront.checkout.create') }}" class="btn btn-primary btn-lg flex w-full items-center justify-center">{{ __('store.proceed_checkout') }}</a>
                <p class="flex items-center justify-center gap-1.5 text-xs text-slate-600">
                    <svg class="size-4 shrink-0 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6zM9 12l2 2 4-4"/></svg>
                    {{ __('store.secure_note') }}
                </p>
            </aside>
        </div>
        <div class="hidden" data-cart-empty>
            <x-empty-state type="cart" :title="__('store.cart_empty')" :text="__('store.cart_empty_text')" :href="route('storefront.home')" :action="__('store.continue_shopping')" class="panel !py-12" />
        </div>
    </div>
</x-layouts.storefront>
