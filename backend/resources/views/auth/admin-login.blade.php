<x-panel.auth :panel="$guard" :title="__('admin.login')" :heading="__('admin.login')" :intro="__('whatsapp.login_help')" :step="__('admin.step_of', ['step' => 1, 'total' => 2])" :panelLabel="$panelLabel ?? null">
    <form data-ajax-form data-validate-url="{{ $validateUrl }}" action="{{ $action }}" method="POST" class="space-y-4" novalidate>
        @csrf
        <div data-field>
            <label for="f-phone" class="form-label">{{ __('whatsapp.fields.phone') }}<span class="ms-0.5 text-panel-danger-solid">*</span></label>
            <div dir="ltr">
                <input type="hidden" name="country" value="{{ $defaultCountry }}">
                <input id="f-phone" type="tel" data-intl-phone="country" name="phone" inputmode="tel" autocomplete="tel-national" autofocus required class="form-control w-full">
            </div>
        </div>
        <x-ui.password name="password" :label="__('whatsapp.fields.password')" required autocomplete="current-password" />
        <x-ui.checkbox name="remember" :label="__('admin.remember_me')" :bool="false" />
        <x-ui.button type="submit" size="lg" class="w-full">{{ __('admin.login') }}</x-ui.button>
        <p class="flex items-start gap-2 rounded-xl bg-panel-surface-sunken px-3 py-2.5 text-xs text-panel-text-muted"><x-ui.icon name="whatsapp" class="mt-0.5 size-4 shrink-0" />{{ __('whatsapp.sec_otp_help') }}</p>
    </form>
</x-panel.auth>
