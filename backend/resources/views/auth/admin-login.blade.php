<x-layouts.base :title="__('admin.login')">
    <div class="grid min-h-screen place-items-center px-4">
        <div class="w-full max-w-sm">
            <div class="mb-6 text-center">
                <a href="{{ url('/') }}" class="inline-block" aria-label="{{ current_tenant()?->name ?? config('app.name') }}">
                    <img src="{{ asset('logo-sm.webp') }}" alt="{{ current_tenant()?->name ?? config('app.name') }}" width="67" height="56" class="mx-auto mb-2 h-14 w-auto object-contain">
                </a>
                <p class="text-sm text-slate-500">{{ __('admin.'.($guard === 'central' ? 'central_panel' : 'tenant_panel')) }}</p>
            </div>
            <x-card>
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
                    <button type="submit" class="btn btn-primary w-full">{{ __('admin.login') }}</button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.base>
