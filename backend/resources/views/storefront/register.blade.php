<x-layouts.storefront :title="__('store.register')" :noindex="true">
    <div class="mx-auto max-w-md">
        <x-card :title="__('store.register')">
            <form data-ajax-form data-validate-url="{{ route('storefront.register.validate') }}" action="{{ route('storefront.register.submit') }}" method="POST" class="space-y-4" novalidate>
                @csrf
                <x-input name="name" :label="__('store.name')" required />
                <x-input name="email" type="email" :label="__('store.email')" required />
                <x-input name="phone" type="tel" :label="__('store.phone')" />
                <x-input name="password" type="password" :label="__('store.password')" required />
                <x-input name="password_confirmation" type="password" :label="__('store.password_confirmation')" required />
                <button class="btn btn-primary w-full">{{ __('store.register') }}</button>
                <p class="text-center text-sm text-slate-500">{{ __('store.has_account') }} <a class="font-semibold text-brand-600" href="{{ route('storefront.login') }}">{{ __('store.login') }}</a></p>
            </form>
        </x-card>
    </div>
</x-layouts.storefront>
