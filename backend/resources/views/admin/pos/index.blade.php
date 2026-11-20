<x-layouts.admin :title="__('pos.title')">
    <x-slot:header><x-ui.page-header :title="__('pos.title')" /></x-slot:header>
    @php($i18n = collect(['cart_empty', 'no_products', 'in_stock', 'out_of_stock', 'orders_count', 'pick_city', 'free', 'eta_days', 'cod_unavailable', 'remove', 'type_to_search', 'no_customers', 'subtotal', 'discount', 'shipping_fee', 'packaging_fee', 'tax', 'cod_fee', 'payment_fee', 'total', 'prepaid_shipping', 'collect_on_delivery'])->mapWithKeys(fn ($k) => [$k => __('pos.'.$k)]))
    <div id="pos" class="grid min-w-0 grid-cols-1 gap-5 max-lg:pb-24 lg:grid-cols-5" data-i18n='@json($i18n)' data-symbol="{{ setting('currency_symbol') }}"
        data-url-products="{{ route('admin.pos.products') }}" data-url-customers="{{ route('admin.pos.customers') }}" data-url-shipping="{{ route('admin.pos.shipping') }}" data-url-quote="{{ route('admin.pos.quote') }}">

        {{-- LEFT: product finder --}}
        <section class="min-w-0 lg:col-span-2">
            <x-ui.card :title="__('pos.products')">
                <input id="pos-product-search" type="search" class="form-control" placeholder="{{ __('pos.search_products') }}" autocomplete="off" autofocus>
                <div id="pos-products" class="mt-4 grid max-h-[70vh] grid-cols-1 gap-3 overflow-y-auto sm:grid-cols-2"></div>
            </x-ui.card>
        </section>

        {{-- RIGHT: the order --}}
        <form id="pos-form" class="flex min-w-0 flex-col gap-5 lg:col-span-3" action="{{ route('admin.pos.store') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf
            {{-- the order form (customer, cart, delivery, payment, summary) is a bottom sheet below 1024 (opened by the floating cart button) and the ordinary cards from 1024 (lg:contents + order) --}}
            <div id="pos-sheet-backdrop" class="fixed inset-0 z-[54] hidden bg-panel-overlay/50 lg:hidden"></div>
            <div id="pos-cart-sheet" role="dialog" aria-label="{{ __('pos.title') }}" class="max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-[55] max-lg:hidden max-lg:max-h-[88dvh] max-lg:overflow-y-auto max-lg:rounded-t-3xl max-lg:bg-panel-bg max-lg:p-4 max-lg:pb-[calc(1rem+env(safe-area-inset-bottom))] max-lg:shadow-panel-overlay max-lg:data-[open]:block lg:contents">
            <div class="mb-3 flex items-center justify-between lg:hidden"><span class="mx-auto h-1 w-10 rounded-full bg-panel-border-strong" aria-hidden="true"></span></div>
            <div class="mb-3 flex items-center justify-between lg:hidden"><h3 class="text-panel-h3 font-bold text-panel-text">{{ __('pos.title') }}</h3><button type="button" id="pos-sheet-close" class="ibtn ibtn-sm" aria-label="{{ __('ui.close') }}"><x-ui.icon name="x" /></button></div>
            <x-ui.card :title="__('pos.customer')" class="max-lg:mb-4 lg:order-1">
                <x-slot:actions><button type="button" class="btn-sm btn-secondary" data-modal-open="pos-customer-modal">+ {{ __('pos.new_customer') }}</button></x-slot:actions>
                <div data-field>
                    <select id="pos-customer" name="customer_id" placeholder="{{ __('pos.search_customer') }}"></select>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('pos.cart')" class="max-lg:mb-4 lg:order-2">
                <x-slot:actions><button type="button" id="pos-clear" class="btn-sm btn-secondary">{{ __('pos.clear') }}</button></x-slot:actions>
                <div id="pos-cart" class="divide-y divide-panel-border"></div>
            </x-ui.card>

            <x-ui.card :title="__('pos.delivery')" class="max-lg:mb-4 lg:order-3">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="name" :label="__('pos.recipient_name')" required />
                    <x-ui.phone name="phone" :label="__('pos.recipient_phone')" required />
                    <x-ui.select name="city_id" :label="__('pos.city')" :options="$cities" :placeholder="__('admin.select')" search required />
                    <x-ui.input name="address" :label="__('pos.address')" required />
                </div>
                <input type="hidden" name="email">
                <x-ui.textarea name="notes" :label="__('pos.notes')" rows="2" class="mt-4" />
                <div class="mt-4" data-field>
                    <label class="form-label">{{ __('pos.shipping') }}<span class="text-panel-danger-fg">*</span></label>
                    <div id="pos-shipping" class="grid gap-2 sm:grid-cols-2"></div>
                    <input type="hidden" name="shipping_company_id">
                </div>
            </x-ui.card>

            <x-ui.card :title="__('pos.payment')" class="max-lg:mb-4 lg:order-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select name="payment_method_id" :label="__('pos.payment_method')" :options="$methods" :placeholder="__('admin.select')" required />
                    <div id="pos-prepay" class="hidden"><x-ui.select name="prepay_method_id" :label="__('pos.prepay_method')" :options="$prepayMethods" :placeholder="__('admin.select')" /></div>
                    <div id="pos-proof" class="hidden" data-field>
                        <label class="form-label" for="f-proof">{{ __('pos.proof') }}<span class="text-panel-danger-fg">*</span></label>
                        <input id="f-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-control">
                    </div>
                    <div data-field>
                        <label class="form-label" for="f-discount_code">{{ __('pos.discount_code') }}</label>
                        <input id="f-discount_code" name="discount_code" class="form-control uppercase" autocomplete="off">
                    </div>
                    @if ($packaging)
                        <x-ui.select name="packaging_option_id" :label="__('pos.packaging')" :options="$packaging" :placeholder="__('pos.none')" />
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card :title="__('pos.summary')" class="lg:sticky lg:bottom-4 lg:z-10 lg:order-5">
                <p id="pos-quote-error" class="mb-3 hidden rounded-lg bg-panel-danger-bg px-3 py-2 text-sm text-panel-danger-fg"></p>
                <dl id="pos-totals" class="space-y-1 text-sm"></dl>
                <button type="submit" id="pos-submit" class="btn btn-primary mt-4 w-full py-3 text-base">{{ __('pos.place_order') }}</button>
            </x-ui.card>
            </div>
        </form>
    </div>

    {{-- floating cart button (below 1024): item count + total, opens the cart sheet; hidden while the cart is empty (pos.js) --}}
    <div id="pos-fab-wrap" class="hidden lg:hidden">
        <button type="button" id="pos-fab" aria-controls="pos-cart-sheet" class="btn btn-primary btn-lg fixed inset-x-4 bottom-[calc(var(--size-bottom-nav)+env(safe-area-inset-bottom)+0.75rem)] z-40 justify-between shadow-panel-lg">
            <span class="inline-flex items-center gap-2"><x-ui.icon name="cart" class="size-5" />{{ __('pos.cart') }} <span class="rounded-full bg-white/25 px-2 text-sm" data-fab-count>0</span></span>
            <span class="font-bold tabular-nums" data-fab-total></span>
        </button>
    </div>

    {{-- quick customer create --}}
    <x-ui.modal id="pos-customer-modal" :title="__('pos.new_customer')">
        <form id="pos-customer-form" action="{{ route('admin.pos.customers.store') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <x-ui.input name="name" :label="__('pos.customer_name')" required />
            <x-ui.phone name="phone" :label="__('pos.customer_phone')" required />
            <x-ui.input name="email" type="email" :label="__('pos.customer_email')" />
            <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('pos.save_customer') }}</button></div>
        </form>
    </x-ui.modal>
</x-layouts.admin>
