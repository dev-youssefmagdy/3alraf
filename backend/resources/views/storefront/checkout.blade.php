<x-layouts.storefront :title="__('store.checkout')" :noindex="true">
    <h1 class="mb-4 text-2xl font-extrabold">{{ __('store.checkout') }}</h1>
    <form data-ajax-form data-checkout data-validate-url="{{ route('storefront.checkout.validate') }}" action="{{ route('storefront.checkout.store') }}" method="POST" class="grid gap-6 lg:grid-cols-[1fr_22rem]" novalidate>
        @csrf
        <div class="space-y-5">
            <x-card :title="__('store.shipping_address')">
                <div class="space-y-3">
                    <div class="space-y-2" data-address-list data-list-url="{{ route('storefront.checkout.addresses') }}">@include('storefront._checkout-addresses')</div>
                    <button type="button" class="btn btn-secondary w-full" data-address-add>+ {{ __('store.add_address') }}</button>
                    <x-input name="notes" :label="__('store.notes')" />
                </div>
            </x-card>

            <x-card :title="__('store.shipping_method')">
                <div class="space-y-2 text-sm" data-shipping-options><p class="text-slate-500">{{ __('store.select_city_hint') }}</p></div>
                <input type="hidden" name="shipping_company_id" value="" data-shipping-input>
            </x-card>

            <x-card :title="__('store.payment_method')">
                <div class="space-y-2 text-sm" data-payment-options>
                    @foreach ($methods as $m)
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 has-[:disabled]:opacity-50" data-method-row>
                            <input type="radio" name="payment_method_id" value="{{ $m->id }}" data-type="{{ $m->type->value }}" data-name="{{ $m->name }}" data-instructions="{{ $m->instructions }}" data-account="{{ $m->account_number }}" class="mt-1 accent-brand-600">
                            <span><b>{{ $m->name }}</b>
                                @php($feeText = (float) $m->fee_percent > 0 ? rtrim(rtrim(number_format((float) $m->fee_percent, 3), '0'), '.').'%' : ((float) $m->fee_fixed > 0 ? number_format((float) $m->fee_fixed, 2) : null))
                                @if ($feeText)<small class="text-slate-500"> ({{ __('store.fee_note', ['fee' => $feeText]) }})</small>@endif
                                @if ($m->instructions)<span class="mt-1 block text-xs text-slate-500">{{ $m->instructions }}</span>@endif
                                @if ($m->account_number)<span class="mt-1 block text-xs text-slate-600">{{ __('store.transfer_to') }}: <b>{{ $m->account_number }}</b></span>@endif
                                <span class="hidden text-xs text-red-600" data-cod-note>{{ __('store.cod_not_for_shipping') }}</span></span>
                        </label>
                    @endforeach
                </div>
                @if ($codUpfront)
                    <div class="mt-4 hidden space-y-2 border-t border-slate-100 pt-3 text-sm" data-prepay>
                        <h3 class="font-bold">{{ __('store.prepay_title') }}</h3>
                        <p class="text-xs text-slate-500">{{ __('store.prepay_hint') }}</p>
                        @foreach ($methods->whereIn('type', [\App\Enums\PaymentMethodType::ONLINE, \App\Enums\PaymentMethodType::WALLET]) as $m)
                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 p-3 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                <input type="radio" name="prepay_method_id" value="{{ $m->id }}" data-type="{{ $m->type->value }}" data-name="{{ $m->name }}" data-instructions="{{ $m->instructions }}" data-account="{{ $m->account_number }}" class="mt-1 accent-brand-600">
                                <span><b>{{ $m->name }}</b>
                                    @if ($m->account_number)<span class="mt-1 block text-xs text-slate-600">{{ __('store.transfer_to') }}: <b>{{ $m->account_number }}</b></span>@endif</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @php($proofKb = (int) config('checkout.proof.max_kb'))
                <div class="mt-4 hidden space-y-2 border-t border-slate-100 pt-3 text-sm" data-offline-proof>
                    <h3 class="font-bold">{{ __('store.offline_proof_title') }}</h3>
                    <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600" data-offline-details></div>
                    <p class="text-xs text-slate-500">{{ __('store.offline_proof_hint', ['mb' => round($proofKb / 1024, 1)]) }}</p>
                    <div data-field>
                        <input type="file" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" disabled
                            data-max-kb="{{ $proofKb }}" data-msg-transfer="{{ __('store.transfer_to') }}" data-msg-type="{{ __('store.proof_client_type') }}" data-msg-size="{{ __('store.proof_client_size', ['mb' => round($proofKb / 1024, 1)]) }}">
                    </div>
                </div>
            </x-card>
        </div>

        <aside class="h-fit space-y-3 rounded-2xl bg-white p-4 ring-1 ring-slate-100 lg:sticky lg:top-32">
            <h2 class="font-extrabold">{{ __('store.order_summary') }}</h2>
            <div class="divide-y divide-slate-100 text-sm" data-checkout-lines></div>
            <div class="flex gap-2" data-field>
                <input type="text" name="discount_code" class="form-control" placeholder="{{ __('store.discount_code') }}" data-code-input>
                <button type="button" class="btn btn-secondary shrink-0" data-apply-code>{{ __('store.apply_code') }}</button>
            </div>
            @include('storefront._packaging')
            <dl class="space-y-2 border-t border-slate-100 pt-3 text-sm" data-totals></dl>
            <button type="submit" class="btn btn-primary w-full !py-3">{{ __('store.place_order') }}</button>
        </aside>
    </form>

    {{-- Add / edit address (outside the checkout form: forms cannot nest) --}}
    <x-modal id="address-modal" :title="__('store.add_address')" data-title-add="{{ __('store.add_address') }}" data-title-edit="{{ __('store.edit_address') }}">
        <form data-ajax-form data-address-form data-validate-url="{{ route('storefront.account.addresses.validate') }}" data-store-url="{{ route('storefront.account.addresses.store') }}" action="{{ route('storefront.account.addresses.store') }}" method="POST" class="grid gap-3 sm:grid-cols-2" novalidate>
            @csrf
            <input type="hidden" name="inline" value="1">
            <x-input name="label" :label="__('store.label')" class="sm:col-span-2" />
            <x-input name="name" :label="__('store.name')" :value="$customer->name" required />
            <x-input name="phone" type="tel" :label="__('store.phone')" :value="$customer->phone" required />
            <x-select name="city_id" :label="__('store.city')" :options="$cities->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()" :placeholder="__('store.select_city')" required class="sm:col-span-2" />
            <x-input name="address" :label="__('store.address')" required class="sm:col-span-2" />
            <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_default" value="1" data-bool class="accent-brand-600"> {{ __('store.set_default') }}</label>
            <div class="flex justify-end gap-2 sm:col-span-2">
                <button type="button" class="btn btn-secondary" data-modal-close>{{ __('admin.cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('store.save') }}</button>
            </div>
        </form>
    </x-modal>
</x-layouts.storefront>
