<x-layouts.base :title="__('store.register')" :lite="true">
    <div class="grid min-h-screen place-items-center px-4 py-8">
        <div class="w-full max-w-sm">
            <div class="mb-6 text-center">
                <a href="{{ route('storefront.home') }}" class="inline-block" aria-label="{{ current_tenant()?->name ?? config('app.name') }}">
                    <img src="{{ asset('logo-sm.webp') }}" alt="{{ current_tenant()?->name ?? config('app.name') }}" width="67" height="56" class="mx-auto mb-2 h-14 w-auto object-contain">
                </a>
                <p class="text-sm text-slate-500">{{ __('store.register') }}</p>
            </div>
            <x-card>
                <form data-ajax-form data-validate-url="{{ route('storefront.register.validate') }}" action="{{ route('storefront.register.submit') }}" method="POST" class="space-y-4" novalidate>
                    @csrf
                    <x-input name="name" :label="__('store.name')" required autofocus />
                    <x-input name="email" type="email" :label="__('store.email')" required autocomplete="username" />
                    <x-phone-input name="phone" :label="__('store.phone')" />
                    <x-input name="password" type="password" :label="__('store.password')" required autocomplete="new-password" />
                    <x-input name="password_confirmation" type="password" :label="__('store.password_confirmation')" required autocomplete="new-password" />
                    <button type="submit" class="btn btn-primary w-full">{{ __('store.register') }}</button>
                </form>
                @include('storefront._google-button')
            </x-card>
            <p class="mt-4 text-center text-sm text-slate-500">{{ __('store.has_account') }} <a class="font-semibold text-brand-600" href="{{ route('storefront.login') }}">{{ __('store.login') }}</a></p>
        </div>
    </div>
</x-layouts.base>
