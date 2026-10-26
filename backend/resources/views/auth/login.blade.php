@php
    $titleKey = ['central' => 'central_panel', 'tenant' => 'tenant_panel', 'web' => 'customer_login'][$guard];
@endphp
<x-layouts.base :title="__('admin.login')">
    <div class="grid min-h-screen place-items-center px-4">
        <div class="w-full max-w-sm">
            <div class="mb-6 text-center">
                <a href="{{ $guard === 'web' ? route('storefront.home') : url('/') }}" class="inline-block" aria-label="{{ current_tenant()?->name ?? config('app.name') }}">
                    <img src="{{ asset('logo.png') }}" alt="{{ current_tenant()?->name ?? config('app.name') }}" class="mx-auto mb-2 h-14 w-auto object-contain">
                </a>
                <h1 class="text-lg font-bold">{{ current_tenant()?->name ?? config('app.name') }}</h1>
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
                @if ($guard === 'web') @include('storefront._google-button') @endif
            </x-card>
            @if ($guard === 'web')<p class="mt-4 text-center text-sm text-slate-500">{{ __('store.no_account') }} <a class="font-semibold text-brand-600" href="{{ route('storefront.register') }}">{{ __('store.register') }}</a></p>@endif
        </div>
    </div>
</x-layouts.base>
