@php
    $titleKey = $guard === 'web' ? 'customer_login' : 'admin_panel';
@endphp
<x-layouts.base :title="__('admin.login')" :lite="$guard === 'web'">
    @if ($guard === 'web')
        {{-- customer login: storefront design (28:9480 / 28:9520); the admin login below is unchanged --}}
        <x-auth-shell :heading="__('admin.'.$titleKey)" :intro="__('store.login_intro')">
            <form data-ajax-form data-validate-url="{{ $validateUrl }}" action="{{ $action }}" method="POST" class="space-y-4" novalidate>
                @csrf
                <x-input name="email" type="email" :label="__('admin.fields.email')" required autofocus autocomplete="username" class="space-y-1" />
                <x-input name="password" type="password" :label="__('admin.fields.password')" required autocomplete="current-password" class="space-y-1" />
                <label class="flex min-h-11 items-center gap-3 text-sm font-semibold text-slate-950">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-[22px] rounded-md border-slate-300 accent-brand-600">
                    {{ __('admin.remember_me') }}
                </label>
                <button type="submit" class="btn btn-primary btn-lg w-full">{{ __('admin.login') }}</button>
            </form>
            @include('storefront._google-button')
            <p class="mt-5 text-center text-sm text-slate-600">{{ __('store.no_account') }} <a class="link" href="{{ route('storefront.register') }}">{{ __('store.register') }}</a></p>
        </x-auth-shell>
    @else
    <div class="grid min-h-screen place-items-center px-4">
        <div class="w-full max-w-sm">
            <div class="mb-6 text-center">
                <a href="{{ url('/') }}" class="inline-block" aria-label="{{ setting('name') ?? config('app.name') }}">
                    <img src="{{ asset('logo-sm.webp') }}" alt="{{ setting('name') ?? config('app.name') }}" width="67" height="56" class="mx-auto mb-2 h-14 w-auto object-contain">
                </a>
                <p class="text-sm text-slate-500">{{ __('admin.'.$titleKey) }}</p>
            </div>
            <x-card>
                <form data-ajax-form data-validate-url="{{ $validateUrl }}" action="{{ $action }}" method="POST" class="space-y-4" novalidate>
                    @csrf
                    <x-input name="email" type="email" :label="__('admin.fields.email')" required autofocus autocomplete="username" />
                    <x-input name="password" type="password" :label="__('admin.fields.password')" required autocomplete="current-password" />
                    <x-checkbox name="remember" :label="__('admin.remember_me')" :bool="false" />
                    <button type="submit" class="btn btn-primary w-full">{{ __('admin.login') }}</button>
                </form>
            </x-card>
        </div>
    </div>
    @endif
</x-layouts.base>
