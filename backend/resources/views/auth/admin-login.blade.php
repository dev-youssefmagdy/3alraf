<x-panel.auth :panel="$guard" :title="__('admin.login')">
    <span class="inline-flex rounded-full bg-panel-soft px-3 py-1 text-xs font-bold text-panel-text">{{ $panelLabel ?? __('admin.admin_panel') }}</span>
    <h1 class="mt-3 text-2xl font-extrabold text-slate-950">{{ __('admin.login') }}</h1>
    <p class="mb-6 mt-1 text-sm text-slate-500">{{ __('admin.step_of', ['step' => 1, 'total' => 2]) }}</p>

                <form data-ajax-form data-validate-url="{{ $validateUrl }}" action="{{ $action }}" method="POST" class="space-y-4" novalidate>
                    @csrf
                    <div data-field>
                        <label for="f-phone" class="form-label">{{ __('whatsapp.fields.phone') }}<span class="text-red-500">*</span></label>
                        <div class="" dir="ltr">
                            <input type="hidden" name="country" value="{{ $defaultCountry }}">
                            <input id="f-phone" type="tel" data-intl-phone="country" name="phone" inputmode="tel" autocomplete="tel-national" autofocus required class="form-control w-full">
                        </div>
                    </div>
                    <x-input name="password" type="password" :label="__('whatsapp.fields.password')" required autocomplete="current-password" />
                    <x-checkbox name="remember" :label="__('admin.remember_me')" :bool="false" />
                    <button type="submit" class="btn btn-primary w-full !py-3">{{ __('admin.login') }}</button>
                </form>
</x-panel.auth>
