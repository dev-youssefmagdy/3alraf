@php
    // public program page (indexable): bonus figures come from the live settings; whole amounts are shown without decimals
    $money = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0.0 ? 0 : 2).' '.setting('currency_symbol');
    $bonusArgs = $bonus ? ['amount' => $money($bonus['amount']), 'threshold' => $money($bonus['threshold'])] : [];
    $metaDesc = $bonus ? __('growth.register_meta_bonus', ['store' => setting('name')] + $bonusArgs) : __('growth.register_meta', ['store' => setting('name')]);
@endphp
{{-- Affiliate register: panel auth layout (brand side + form card) like the affiliate login; the programme intro is the heading, the bonus benefit (data-bonus-benefit) a callout,
     then name, email, phone, password, confirmation -> Register -> login link. Indexable public page; exactly one H1 (the heading). --}}
<x-panel.auth panel="affiliate" :lite="true" :title="__('growth.register_title')" :heading="__('growth.join_program')" :intro="__('growth.register_intro')"
    :description="$metaDesc" :indexable="true" alt-route="storefront.affiliate.register">
    @if ($bonus)
        <x-ui.alert tone="warning" class="mb-5" data-bonus-benefit><span class="font-bold">{{ __('growth.register_bonus', $bonusArgs) }}</span></x-ui.alert>
    @endif
    <form data-ajax-form data-validate-url="{{ route('storefront.affiliate.register.validate') }}" action="{{ route('storefront.affiliate.register.submit') }}" method="POST" class="space-y-4" novalidate>
        @csrf
        <x-ui.input name="name" :label="__('admin.fields.name')" required autocomplete="name" />
        <x-ui.input name="email" type="email" :label="__('admin.fields.email')" required autocomplete="email" dir="ltr" />
        <x-ui.phone name="phone" :label="__('ops.fields.phone')" />
        <x-ui.password name="password" :label="__('admin.fields.password')" required autocomplete="new-password" />
        <x-ui.password name="password_confirmation" :label="__('growth.fields.password_confirmation')" required autocomplete="new-password" />
        <x-ui.button type="submit" size="lg" class="w-full">{{ __('growth.register') }}</x-ui.button>
    </form>
    <p class="mt-5 text-center text-sm text-panel-text-muted"><x-ui.link :href="route('storefront.affiliate.login')">{{ __('admin.login') }}</x-ui.link></p>
</x-panel.auth>
