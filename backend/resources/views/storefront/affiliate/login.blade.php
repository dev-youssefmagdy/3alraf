<x-layouts.storefront :title="__('growth.affiliate_login')" :noindex="true">
    <div class="mx-auto max-w-sm">
        <x-card :title="__('growth.affiliate_login')">
            <form data-ajax-form data-validate-url="{{ route('storefront.affiliate.login.validate') }}" action="{{ route('storefront.affiliate.login.submit') }}" method="POST" class="space-y-4" novalidate>
                @csrf
                <x-input name="email" type="email" :label="__('admin.fields.email')" required autocomplete="username" />
                <x-input name="password" type="password" :label="__('admin.fields.password')" required autocomplete="current-password" />
                <x-checkbox name="remember" :label="__('admin.remember_me')" :bool="false" />
                <button type="submit" class="btn btn-primary w-full">{{ __('admin.login') }}</button>
            </form>
            <p class="mt-4 text-center text-sm text-slate-500">{{ __('growth.no_account') }} <a class="text-brand-600" href="{{ route('storefront.affiliate.register') }}">{{ __('growth.join_program') }}</a></p>
        </x-card>
    </div>
</x-layouts.storefront>
