<x-layouts.base :title="__('store.register')" :lite="true">
    <x-auth-shell :heading="__('store.register')" :intro="__('store.register_intro')">
        <form data-ajax-form data-validate-url="{{ route('storefront.register.validate') }}" action="{{ route('storefront.register.submit') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <x-input name="name" :label="__('store.name')" required autofocus autocomplete="name" class="space-y-1" />
            <x-input name="email" type="email" :label="__('store.email')" required autocomplete="username" class="space-y-1" />
            <x-phone-input name="phone" :label="__('store.phone')" class="space-y-1" />
            <x-input name="password" type="password" :label="__('store.password')" required autocomplete="new-password" class="space-y-1" />
            <x-input name="password_confirmation" type="password" :label="__('store.password_confirmation')" required autocomplete="new-password" class="space-y-1" />
            <button type="submit" class="btn btn-primary btn-lg w-full">{{ __('store.register') }}</button>
        </form>
        @include('storefront._google-button')
        <p class="mt-5 text-center text-sm text-slate-600">{{ __('store.has_account') }} <a class="link" href="{{ route('storefront.login') }}">{{ __('store.login') }}</a></p>
    </x-auth-shell>
</x-layouts.base>
