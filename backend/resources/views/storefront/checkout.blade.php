@php
    $svg = fn (string $d, string $cls = 'size-5') => '<svg class="'.$cls.' shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="'.$d.'"/></svg>';
    $icons = [
        'cod' => 'M3 7h18v10H3zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 10v4M18 10v4',
        'online' => 'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6zM9 12l2 2 4-4',
        'wallet' => 'M7 2h10a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1zM11 18h2',
    ];
    $step = fn (int $n) => '<span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-600 font-display text-base font-extrabold text-white" aria-hidden="true">'.$n.'</span>';
    $choice = 'flex cursor-pointer items-start gap-3 rounded-2xl border-2 border-slate-200 bg-white p-3.5 transition hover:border-brand-300 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-pop-500/45';
    $radio = 'mt-0.5 size-[22px] shrink-0 accent-brand-600';
@endphp
<x-layouts.storefront :title="__('store.checkout')" :noindex="true">
    {{-- design 25:5878 / 25:6255, errors 32:2080: numbered steps + sticky summary 420 --}}
    <h1 class="page-title mb-5 lg:mb-8">{{ __('store.checkout') }}</h1>
    <form data-ajax-form data-checkout data-validate-url="{{ route('storefront.checkout.validate') }}" action="{{ route('storefront.checkout.store') }}" method="POST" class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_420px] lg:gap-8" novalidate>
        @csrf
        <div class="min-w-0 space-y-5">
            <div class="hidden items-center gap-2 rounded-2xl bg-red-50 px-4 py-3 text-sm font-bold text-red-700" data-checkout-errors role="alert" tabindex="-1">
                {!! $svg('M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z') !!}
                <span>{{ __('store.fix_before_order') }}</span>
            </div>

            <section class="panel space-y-3 border-2 lg:p-6 [&.is-error]:border-red-600" data-step="address" aria-labelledby="co-address">
                <h2 id="co-address" class="flex items-center gap-3 font-display text-[22px]/[30px] font-bold text-slate-950">{!! $step(1) !!}{{ __('store.shipping_address') }}</h2>
                <div class="space-y-3" data-field>
                    <div class="space-y-3" data-address-list data-list-url="{{ route('storefront.checkout.addresses') }}">@include('storefront._checkout-addresses')</div>
                </div>
                <button type="button" class="flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-brand-300 bg-white text-sm font-bold text-brand-600 transition hover:bg-brand-50" data-address-add>
                    {!! $svg('M12 5v14M5 12h14', 'size-4') !!}{{ __('store.add_address') }}
                </button>
                <x-input name="notes" :label="__('store.notes')" />
            </section>

            <section class="panel space-y-3 border-2 lg:p-6" aria-labelledby="co-shipping">
                <h2 id="co-shipping" class="flex items-center gap-3 font-display text-[22px]/[30px] font-bold text-slate-950">{!! $step(2) !!}{{ __('store.shipping_method') }}</h2>
                <div class="space-y-3 text-sm" data-shipping-options><p class="rounded-2xl bg-slate-100 px-4 py-3 text-slate-600">{{ __('store.select_city_hint') }}</p></div>
                <input type="hidden" name="shipping_company_id" value="" data-shipping-input>
            </section>

            <section class="panel space-y-3 border-2 lg:p-6" aria-labelledby="co-payment">
                <h2 id="co-payment" class="flex items-center gap-3 font-display text-[22px]/[30px] font-bold text-slate-950">{!! $step(3) !!}{{ __('store.payment_method') }}</h2>
                {{-- data-field: a validation error goes under the whole group, not inside the last choice card --}}
                <div class="space-y-3 text-sm" data-payment-options data-field>
                    @foreach ($methods as $m)
                        @php($feeText = (float) $m->fee_percent > 0 ? rtrim(rtrim(number_format((float) $m->fee_percent, 3), '0'), '.').'%' : ((float) $m->fee_fixed > 0 ? number_format((float) $m->fee_fixed, 2) : null))
                        <label class="{{ $choice }}" data-method-row>
                            <input type="radio" name="payment_method_id" value="{{ $m->id }}" data-type="{{ $m->type->value }}" data-name="{{ $m->name }}" data-instructions="{{ $m->instructions }}" data-account="{{ $m->account_number }}" class="{{ $radio }}">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                                    <span class="flex items-center gap-2 text-base font-bold text-slate-950"><span class="text-mint-700">{!! $svg($icons[$m->type->value] ?? $icons['online']) !!}</span>{{ $m->name }}</span>
                                    <span class="text-xs font-bold text-mint-700">{{ $feeText ? __('store.fee_note', ['fee' => $feeText]) : __('store.no_fee') }}</span>
                                </span>
                                @if ($m->instructions)<span class="mt-1 block text-xs/[18px] text-slate-600">{{ $m->instructions }}</span>@endif
                                @if ($m->account_number)<span class="mt-1 block text-xs text-slate-600">{{ __('store.transfer_to') }}: <b class="text-slate-950"><bdi dir="ltr">{{ $m->account_number }}</bdi></b></span>@endif
                                <span class="mt-1 hidden w-fit rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-bold text-red-700" data-cod-note>{{ __('store.cod_not_for_shipping') }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @if ($codUpfront)
                    <div class="hidden space-y-3 rounded-2xl bg-mint-100 p-4 text-sm" data-prepay>
                        <div>
                            <h3 class="font-bold text-mint-700">{{ __('store.prepay_title') }}</h3>
                            <p class="mt-0.5 text-xs/[18px] text-mint-700">{{ __('store.prepay_hint') }}</p>
                        </div>
                        @foreach ($methods->whereIn('type', [\App\Enums\PaymentMethodType::ONLINE, \App\Enums\PaymentMethodType::WALLET]) as $m)
                            <label class="{{ $choice }}">
                                <input type="radio" name="prepay_method_id" value="{{ $m->id }}" data-type="{{ $m->type->value }}" data-name="{{ $m->name }}" data-instructions="{{ $m->instructions }}" data-account="{{ $m->account_number }}" class="{{ $radio }}">
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-2 font-bold text-slate-950"><span class="text-mint-700">{!! $svg($icons[$m->type->value] ?? $icons['online']) !!}</span>{{ $m->name }}</span>
                                    @if ($m->account_number)<span class="mt-1 block text-xs text-slate-600">{{ __('store.transfer_to') }}: <b class="text-slate-950"><bdi dir="ltr">{{ $m->account_number }}</bdi></b></span>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @php($proofKb = (int) config('checkout.proof.max_kb'))
                <div class="hidden space-y-2 text-sm" data-offline-proof>
                    <h3 class="font-bold text-slate-950">{{ __('store.offline_proof_title') }}</h3>
                    <div class="space-y-0.5 rounded-2xl bg-slate-100 px-4 py-3 text-xs/[18px] text-slate-600 [&_b]:text-sm [&_b]:text-slate-950" data-offline-details></div>
                    <div data-field>
                        <label class="flex cursor-pointer flex-col items-center gap-1 rounded-2xl border-2 border-dashed border-brand-300 bg-white px-4 py-6 text-center transition hover:bg-brand-50 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-pop-500/45 has-[.is-invalid]:border-red-600">
                            <span class="text-brand-600">{!! $svg('M12 16V4M7 9l5-5 5 5M4 20h16', 'size-6') !!}</span>
                            <span class="font-bold text-brand-600" data-proof-name>{{ __('store.upload_proof_cta') }}</span>
                            <span class="text-xs/[18px] text-slate-600">{{ __('store.offline_proof_hint', ['mb' => round($proofKb / 1024, 1)]) }}</span>
                            <input type="file" name="proof" class="sr-only" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" disabled
                                data-max-kb="{{ $proofKb }}" data-msg-transfer="{{ __('store.transfer_to') }}" data-msg-type="{{ __('store.proof_client_type') }}" data-msg-size="{{ __('store.proof_client_size', ['mb' => round($proofKb / 1024, 1)]) }}">
                        </label>
                    </div>
                </div>
            </section>
        </div>

        <aside class="panel space-y-4 lg:sticky lg:top-40 lg:p-6" aria-labelledby="co-summary">
            <h2 id="co-summary" class="font-display text-[22px]/[30px] font-bold text-slate-950">{{ __('store.order_summary') }}</h2>
            <div class="space-y-3 text-sm" data-checkout-lines></div>
            <div data-field>
                <label for="co-code" class="form-label">{{ __('store.discount_code') }}</label>
                <div class="mt-1 flex gap-2">
                    <input id="co-code" type="text" name="discount_code" class="form-control min-w-0 flex-1" placeholder="{{ __('store.discount_code') }}" autocomplete="off" data-code-input>
                    <button type="button" class="btn btn-secondary shrink-0" data-apply-code>{{ __('store.apply_code') }}</button>
                </div>
            </div>
            <p class="-mt-2 hidden items-center gap-1.5 text-xs font-bold text-emerald-700" data-code-ok>{!! $svg('m5 12 5 5L20 7', 'size-4') !!}{{ __('store.code_applied') }}</p>
            @include('storefront._packaging')
            <dl class="space-y-2.5 border-t border-slate-200 pt-4 text-sm" data-totals></dl>
            <button type="submit" class="btn btn-primary btn-lg w-full">{{ __('store.place_order') }}</button>
            <p class="text-center text-xs/[18px] text-slate-600">{{ __('store.order_policy_note') }}</p>
        </aside>
    </form>

    {{-- Add / edit address (outside the checkout form: forms cannot nest). Bottom sheet below 640px, centred 520 modal above (design 32:2147 / 32:2269). --}}
    <div id="address-modal" data-modal class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4 max-sm:items-end max-sm:p-0">
        <div class="relative max-h-[92vh] w-full max-w-[520px] overflow-y-auto rounded-3xl bg-white p-5 shadow-overlay max-sm:max-w-none max-sm:rounded-b-none max-sm:pb-[calc(1.25rem+env(safe-area-inset-bottom))] sm:p-6" role="dialog" aria-modal="true" aria-labelledby="address-modal-title" data-title-add="{{ __('store.add_address') }}" data-title-edit="{{ __('store.edit_address') }}">
            <span class="mx-auto -mt-2 mb-3 block h-1.5 w-10 rounded-full bg-slate-200 sm:hidden" aria-hidden="true"></span>
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 id="address-modal-title" class="font-display text-[22px]/[30px] font-bold text-slate-950">{{ __('store.add_address') }}</h3>
                <button type="button" class="icon-btn icon-btn-outline" data-modal-close aria-label="{{ __('store.close') }}">{!! $svg('M6 6l12 12M18 6 6 18') !!}</button>
            </div>
            <form data-ajax-form data-address-form data-validate-url="{{ route('storefront.account.addresses.validate') }}" data-store-url="{{ route('storefront.account.addresses.store') }}" action="{{ route('storefront.account.addresses.store') }}" method="POST" class="grid gap-4 sm:grid-cols-2" novalidate>
                @csrf
                <input type="hidden" name="inline" value="1">
                <div class="sm:col-span-2"><x-input name="label" :label="__('store.label')" /></div>
                <div><x-input name="name" :label="__('store.name')" :value="$customer->name" required /></div>
                <div><x-phone-input name="phone" :label="__('store.phone')" :value="$customer->phone" required /></div>
                <div class="sm:col-span-2"><x-select name="city_id" :label="__('store.city')" :options="$cities->mapWithKeys(fn ($c) => [$c->id => $c->name])->all()" :placeholder="__('store.select_city')" required /></div>
                <div class="sm:col-span-2"><x-input name="address" :label="__('store.address')" required /></div>
                <label class="flex min-h-11 items-center gap-3 text-sm font-semibold text-slate-950 sm:col-span-2"><input type="checkbox" name="is_default" value="1" data-bool class="size-[22px] rounded-md accent-brand-600"> {{ __('store.set_default') }}</label>
                <div class="grid grid-cols-2 gap-3 sm:col-span-2">
                    <button type="submit" class="btn btn-primary btn-lg">{{ __('store.save') }}</button>
                    <button type="button" class="btn btn-secondary btn-lg" data-modal-close>{{ __('admin.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.addEventListener('cart:begin', function (e) { var c = e.detail || {}; window.sfTrack && sfTrack('begin_checkout', {value: Number(c.totals ? c.totals.subtotal : 0), items: (c.items || []).map(function (i) { return {id: String(i.product_id || i.id), name: i.name, price: Number(i.unit_price), qty: i.qty}; })}); });
    </script>
</x-layouts.storefront>
