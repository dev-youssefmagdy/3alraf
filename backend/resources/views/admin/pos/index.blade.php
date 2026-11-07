<x-layouts.admin guard="tenant" :title="__('pos.title')">
    @php($i18n = collect(['cart_empty', 'no_products', 'in_stock', 'out_of_stock', 'orders_count', 'pick_city', 'free', 'eta_days', 'cod_unavailable', 'remove', 'type_to_search', 'no_customers', 'subtotal', 'discount', 'shipping_fee', 'packaging_fee', 'tax', 'cod_fee', 'payment_fee', 'total', 'prepaid_shipping', 'collect_on_delivery'])->mapWithKeys(fn ($k) => [$k => __('pos.'.$k)]))
    <div id="pos" class="grid gap-5 lg:grid-cols-5" data-i18n='@json($i18n)' data-symbol="{{ current_tenant()->currency_symbol }}"
        data-url-products="{{ route('tenant.pos.products') }}" data-url-customers="{{ route('tenant.pos.customers') }}" data-url-shipping="{{ route('tenant.pos.shipping') }}" data-url-quote="{{ route('tenant.pos.quote') }}">

        {{-- LEFT: product finder --}}
        <section class="lg:col-span-2">
            <x-card :title="__('pos.products')">
                <input id="pos-product-search" type="search" class="form-control" placeholder="{{ __('pos.search_products') }}" autocomplete="off" autofocus>
                <div id="pos-products" class="mt-4 grid max-h-[70vh] gap-3 overflow-y-auto sm:grid-cols-2"></div>
            </x-card>
        </section>

        {{-- RIGHT: the order --}}
        <form id="pos-form" class="space-y-5 lg:col-span-3" action="{{ route('tenant.pos.store') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf
            <x-card :title="__('pos.customer')">
                <x-slot:actions><button type="button" class="btn-sm btn-secondary" data-modal-open="pos-customer-modal">+ {{ __('pos.new_customer') }}</button></x-slot:actions>
                <div data-field>
                    <select id="pos-customer" name="customer_id" placeholder="{{ __('pos.search_customer') }}"></select>
                </div>
            </x-card>

            <x-card :title="__('pos.cart')">
                <x-slot:actions><button type="button" id="pos-clear" class="btn-sm btn-secondary">{{ __('pos.clear') }}</button></x-slot:actions>
                <div id="pos-cart" class="divide-y divide-slate-100"></div>
            </x-card>

            <x-card :title="__('pos.delivery')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="name" :label="__('pos.recipient_name')" required />
                    <x-phone-input name="phone" :label="__('pos.recipient_phone')" required />
                    <x-select name="city_id" :label="__('pos.city')" :options="$cities" :placeholder="__('admin.select')" search required />
                    <x-input name="address" :label="__('pos.address')" required />
                </div>
                <input type="hidden" name="email">
                <x-textarea name="notes" :label="__('pos.notes')" rows="2" class="mt-4" />
                <div class="mt-4" data-field>
                    <label class="form-label">{{ __('pos.shipping') }}<span class="text-red-500">*</span></label>
                    <div id="pos-shipping" class="grid gap-2 sm:grid-cols-2"></div>
                    <input type="hidden" name="shipping_company_id">
                </div>
            </x-card>

            <x-card :title="__('pos.payment')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select name="payment_method_id" :label="__('pos.payment_method')" :options="$methods" :placeholder="__('admin.select')" required />
                    <div id="pos-prepay" class="hidden"><x-select name="prepay_method_id" :label="__('pos.prepay_method')" :options="$prepayMethods" :placeholder="__('admin.select')" /></div>
                    <div id="pos-proof" class="hidden" data-field>
                        <label class="form-label" for="f-proof">{{ __('pos.proof') }}<span class="text-red-500">*</span></label>
                        <input id="f-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-control">
                    </div>
                    <div data-field>
                        <label class="form-label" for="f-discount_code">{{ __('pos.discount_code') }}</label>
                        <input id="f-discount_code" name="discount_code" class="form-control uppercase" autocomplete="off">
                    </div>
                    @if ($packaging)
                        <x-select name="packaging_option_id" :label="__('pos.packaging')" :options="$packaging" :placeholder="__('pos.none')" />
                    @endif
                </div>
            </x-card>

            <x-card :title="__('pos.summary')" class="lg:sticky lg:bottom-4">
                <p id="pos-quote-error" class="mb-3 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                <dl id="pos-totals" class="space-y-1 text-sm"></dl>
                <button type="submit" id="pos-submit" class="btn btn-primary mt-4 w-full py-3 text-base">{{ __('pos.place_order') }}</button>
            </x-card>
        </form>
    </div>

    {{-- quick customer create --}}
    <x-modal id="pos-customer-modal" :title="__('pos.new_customer')">
        <form id="pos-customer-form" action="{{ route('tenant.pos.customers.store') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <x-input name="name" :label="__('pos.customer_name')" required />
            <x-phone-input name="phone" :label="__('pos.customer_phone')" required />
            <x-input name="email" type="email" :label="__('pos.customer_email')" />
            <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('pos.save_customer') }}</button></div>
        </form>
    </x-modal>
</x-layouts.admin>
