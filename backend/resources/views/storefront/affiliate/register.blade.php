@php
    // public program page (indexable): bonus figures come from the live settings; whole amounts are shown without decimals
    $money = fn ($v) => number_format((float) $v, fmod((float) $v, 1) == 0.0 ? 0 : 2).' '.setting('currency_symbol');
    $bonusArgs = $bonus ? ['amount' => $money($bonus['amount']), 'threshold' => $money($bonus['threshold'])] : [];
    $metaDesc = $bonus ? __('growth.register_meta_bonus', ['store' => setting('name')] + $bonusArgs) : __('growth.register_meta', ['store' => setting('name')]);
@endphp
<x-layouts.storefront :title="__('growth.register_title')" :description="$metaDesc">
    {{-- Affiliate register (design 30:11408 / 30:11617): centred 560 card — violet programme hero with the sun bonus benefit (data-bonus-benefit),
         then name, email, phone, password, confirmation → Register → login link. Indexable public page; exactly one H1. --}}
    <div class="mx-auto max-w-[560px] rounded-[32px] border border-slate-200 bg-white p-4 shadow-card sm:p-6 lg:my-6">
        <div class="relative isolate overflow-hidden rounded-3xl bg-violet-500 p-5 text-white sm:p-6">
            <span class="pointer-events-none absolute -end-8 -top-10 -z-10 size-32 rounded-full bg-violet-400" aria-hidden="true"></span>
            <span class="pointer-events-none absolute -bottom-10 start-1/3 -z-10 size-20 rounded-full bg-pop-500/60" aria-hidden="true"></span>
            <h1 class="font-display text-[28px]/9 font-extrabold lg:text-[32px]/[40px]">{{ __('growth.join_program') }}</h1>
            <p class="mt-2 text-sm/[22px] text-white">{{ __('growth.register_intro') }}</p>
            @if ($bonus)
                <p data-bonus-benefit class="mt-4 flex items-start gap-2.5 rounded-2xl bg-sun-400 p-3 text-sm/[22px] font-bold text-slate-950">
                    <svg class="mt-0.5 size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 21V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/></svg>
                    <span>{{ __('growth.register_bonus', $bonusArgs) }}</span>
                </p>
            @endif
        </div>
        <form data-ajax-form data-validate-url="{{ route('storefront.affiliate.register.validate') }}" action="{{ route('storefront.affiliate.register.submit') }}" method="POST" class="mt-5 space-y-4 sm:px-2" novalidate>
            @csrf
            <x-input name="name" :label="__('admin.fields.name')" required autocomplete="name" class="space-y-1" />
            <x-input name="email" type="email" :label="__('admin.fields.email')" required autocomplete="email" class="space-y-1" />
            <x-phone-input name="phone" :label="__('ops.fields.phone')" class="space-y-1" />
            <x-input name="password" type="password" :label="__('admin.fields.password')" required autocomplete="new-password" class="space-y-1" />
            <x-input name="password_confirmation" type="password" :label="__('growth.fields.password_confirmation')" required autocomplete="new-password" class="space-y-1" />
            <button type="submit" class="btn btn-primary btn-lg w-full">{{ __('growth.register') }}</button>
        </form>
        <p class="mt-5 pb-2 text-center text-sm"><a class="link !text-violet-700" href="{{ route('storefront.affiliate.login') }}">{{ __('admin.login') }}</a></p>
    </div>
</x-layouts.storefront>
