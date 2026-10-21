@php($editing = $tenant->exists)
<x-layouts.admin guard="central" :title="$editing ? __('admin.edit').': '.$tenant->name : __('admin.add_new')">
    <x-card class="max-w-3xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('central.tenants.validate', $editing ? [$tenant] : []) }}"
            action="{{ $editing ? route('central.tenants.update', $tenant) : route('central.tenants.store') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" :label="__('admin.fields.name')" :value="$tenant->name" required />
                <x-input name="slug" :label="__('admin.fields.slug')" :value="$tenant->slug" required :hint="'.'.central_domain()" />
                <x-input name="country" :label="__('admin.fields.country')" :value="$tenant->country" maxlength="2" />
                <x-input name="timezone" :label="__('admin.fields.timezone')" :value="$tenant->timezone" required />
                <x-input name="currency_code" :label="__('admin.fields.currency_code')" :value="$tenant->currency_code" required maxlength="3" />
                <x-input name="currency_symbol" :label="__('admin.fields.currency_symbol')" :value="$tenant->currency_symbol" required />
                <x-input name="currency_rate" type="number" step="0.000001" :label="__('admin.fields.currency_rate')" :value="$tenant->currency_rate" required />
                <x-select name="default_locale" :label="__('admin.fields.default_locale')" :options="$languages->pluck('native_name', 'code')->all()" :value="$tenant->default_locale" required />
            </div>
            <div data-field>
                <div class="form-label">{{ __('admin.fields.locales') }}</div>
                <div class="flex flex-wrap gap-4">
                    @foreach ($languages as $l)
                        <x-checkbox name="locales[]" :value="$l->code" :bool="false" :label="$l->native_name" :checked="in_array($l->code, old('locales', $tenant->locales ?? []))" />
                    @endforeach
                </div>
            </div>
            <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$tenant->is_active" />

            @unless ($editing)
                <div class="rounded-lg border border-dashed border-slate-300 p-4">
                    <p class="font-semibold">{{ __('admin.owner_account') }}</p>
                    <p class="mb-3 text-xs text-slate-500">{{ __('admin.owner_account_hint') }}</p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-input name="admin_name" :label="__('admin.fields.admin_name')" />
                        <x-input name="admin_email" type="email" :label="__('admin.fields.admin_email')" autocomplete="off" />
                        <x-input name="admin_password" type="password" :label="__('admin.fields.admin_password')" autocomplete="new-password" />
                    </div>
                </div>
            @endunless
            <x-form-actions :cancel="route('central.tenants.index')" />
        </form>
    </x-card>
</x-layouts.admin>
