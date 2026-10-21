<x-layouts.storefront :title="__('store.cart_title')" :noindex="true">
    <h1 class="mb-4 text-2xl font-extrabold">{{ __('store.cart_title') }}</h1>
    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]" data-cart-page>
        <div>
            <div class="divide-y divide-slate-100 rounded-2xl bg-white p-2 ring-1 ring-slate-100" data-cart-lines></div>
            <div class="hidden rounded-2xl bg-white p-10 text-center ring-1 ring-slate-100" data-cart-empty>
                <p class="mb-4 text-lg text-slate-500">{{ __('store.cart_empty') }}</p>
                <a href="{{ route('storefront.home') }}" class="btn btn-primary">{{ __('store.continue_shopping') }}</a>
            </div>
            <p class="mt-3 hidden rounded-lg bg-amber-50 p-3 text-sm text-amber-700" data-cart-flash>{{ __('store.flash_note') }}</p>
        </div>
        <aside class="h-fit space-y-3 rounded-2xl bg-white p-4 ring-1 ring-slate-100" data-cart-summary>
            <h2 class="font-extrabold">{{ __('store.order_summary') }}</h2>
            <dl class="space-y-2 text-sm" data-totals></dl>
            <a href="{{ route('storefront.checkout.create') }}" class="btn btn-primary w-full !py-3">{{ __('store.proceed_checkout') }}</a>
            <a href="{{ route('storefront.home') }}" class="block text-center text-sm text-slate-500 hover:text-brand-600">{{ __('store.continue_shopping') }}</a>
        </aside>
    </div>
</x-layouts.storefront>
