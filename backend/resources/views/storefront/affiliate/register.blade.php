@php
    // public program page (indexable): bonus figures come from the live settings; whole amounts are shown without decimals
    $money = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0.0 ? 0 : 2).' '.setting('currency_symbol');
    $bonusArgs = $bonus ? ['amount' => $money($bonus['amount']), 'threshold' => $money($bonus['threshold'])] : [];
    $metaDesc = $bonus ? __('growth.register_meta_bonus', ['store' => setting('name')] + $bonusArgs) : __('growth.register_meta', ['store' => setting('name')]);
@endphp
<x-layouts.storefront :title="__('growth.register_title')" :description="$metaDesc">
    <div class="mx-auto max-w-md">
        <x-card>
            <h1 class="text-xl font-bold text-slate-800">{{ __('growth.join_program') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('growth.register_intro') }}</p>
            @if ($bonus)
                <p data-bonus-benefit class="mt-3 flex items-start gap-2 rounded-lg bg-brand-50 p-3 text-sm font-medium text-brand-700">
                    <svg class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg>
                    <span>{{ __('growth.register_bonus', $bonusArgs) }}</span>
                </p>
            @endif
            <form data-ajax-form data-validate-url="{{ route('storefront.affiliate.register.validate') }}" action="{{ route('storefront.affiliate.register.submit') }}" method="POST" class="mt-4 space-y-4" novalidate>
                @csrf
                <x-input name="name" :label="__('admin.fields.name')" required />
                <x-input name="email" type="email" :label="__('admin.fields.email')" required />
                <x-phone-input name="phone" :label="__('ops.fields.phone')" />
                <x-input name="password" type="password" :label="__('admin.fields.password')" required autocomplete="new-password" />
                <x-input name="password_confirmation" type="password" :label="__('growth.fields.password_confirmation')" required autocomplete="new-password" />
                <button type="submit" class="btn btn-primary w-full">{{ __('growth.register') }}</button>
            </form>
            <p class="mt-4 text-center text-sm text-slate-500"><a class="text-brand-600" href="{{ route('storefront.affiliate.login') }}">{{ __('admin.login') }}</a></p>
        </x-card>
    </div>
</x-layouts.storefront>
