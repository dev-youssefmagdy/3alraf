{{-- Affiliate login (Figma "Login + OTP" › Affiliate): panel auth layout (brand side + form), email + password, remember, join link. --}}
<x-panel.auth panel="affiliate" :lite="true" :title="__('growth.affiliate_login')" :heading="__('growth.affiliate_login')" :intro="__('ui.signin_email_help')">
    <form data-ajax-form data-validate-url="{{ route('storefront.affiliate.login.validate') }}" action="{{ route('storefront.affiliate.login.submit') }}" method="POST" class="space-y-4" novalidate>
        @csrf
        <x-ui.input name="email" type="email" :label="__('admin.fields.email')" required autocomplete="username" dir="ltr" />
        <x-ui.password name="password" :label="__('admin.fields.password')" required autocomplete="current-password" />
        <x-ui.checkbox name="remember" :label="__('admin.remember_me')" :bool="false" :checked="(bool) old('remember')" />
        <x-ui.button type="submit" size="lg" class="w-full">{{ __('admin.login') }}</x-ui.button>
    </form>
    <p class="mt-5 text-center text-sm text-panel-text-muted">{{ __('growth.no_account') }} <x-ui.link :href="route('storefront.affiliate.register')">{{ __('growth.join_program') }}</x-ui.link></p>
</x-panel.auth>
