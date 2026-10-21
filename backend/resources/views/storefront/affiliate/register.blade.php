<x-layouts.storefront :title="__('growth.join_program')">
    <div class="mx-auto max-w-md">
        <x-card :title="__('growth.join_program')">
            <p class="mb-4 text-sm text-slate-500">{{ __('growth.register_intro') }}</p>
            <form data-ajax-form data-validate-url="{{ route('storefront.affiliate.register.validate') }}" action="{{ route('storefront.affiliate.register.submit') }}" method="POST" class="space-y-4" novalidate>
                @csrf
                <x-input name="name" :label="__('admin.fields.name')" required />
                <x-input name="email" type="email" :label="__('admin.fields.email')" required />
                <x-input name="phone" :label="__('ops.fields.phone')" />
                <x-input name="password" type="password" :label="__('admin.fields.password')" required autocomplete="new-password" />
                <x-input name="password_confirmation" type="password" :label="__('growth.fields.password_confirmation')" required autocomplete="new-password" />
                <button type="submit" class="btn btn-primary w-full">{{ __('growth.register') }}</button>
            </form>
            <p class="mt-4 text-center text-sm text-slate-500"><a class="text-brand-600" href="{{ route('storefront.affiliate.login') }}">{{ __('admin.login') }}</a></p>
        </x-card>
    </div>
</x-layouts.storefront>
