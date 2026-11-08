@php($canEdit = auth('admin')->user()?->can('general_settings.edit'))
<x-layouts.admin :title="__('admin.settings_general')">
    <x-card class="max-w-3xl">
        <form data-ajax-form data-method="PUT" data-validate-url="{{ route('admin.general-settings.validate') }}"
            action="{{ route('admin.general-settings.update') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" :label="__('admin.fields.name')" :value="$s['name']" required />
                <x-input name="country" :label="__('admin.fields.country')" :value="$s['country']" maxlength="2" />
                <x-input name="timezone" :label="__('admin.fields.timezone')" :value="$s['timezone']" required />
                <x-input name="currency_code" :label="__('admin.fields.currency_code')" :value="$s['currency_code']" required maxlength="3" />
                <x-input name="currency_symbol" :label="__('admin.fields.currency_symbol')" :value="$s['currency_symbol']" required />
                <x-select name="default_locale" :label="__('admin.fields.default_locale')" :options="$languages->pluck('native_name', 'code')->all()" :value="$s['default_locale']" required />
            </div>
            <div data-field>
                <div class="form-label">{{ __('admin.fields.locales') }}</div>
                <div class="flex flex-wrap gap-4">
                    @foreach ($languages as $l)
                        <x-checkbox name="locales[]" :value="$l->code" :bool="false" :label="$l->native_name" :checked="in_array($l->code, old('locales', $s['locales']))" />
                    @endforeach
                </div>
            </div>
            <div class="space-y-2 rounded-lg border border-dashed border-slate-300 p-4">
                <x-checkbox name="gift_finder_enabled" :label="__('gift_finder.setting')" :checked="$s['gift_finder_enabled']" />
                <p class="text-xs text-slate-500">{{ __('gift_finder.setting_hint') }}</p>
            </div>
            <div class="space-y-2 rounded-lg border border-dashed border-slate-300 p-4">
                <x-checkbox name="customer_cancel_enabled" :label="__('orders.customer_cancel.setting')" :checked="$s['customer_cancel_enabled']" />
                <p class="text-xs text-slate-500">{{ __('orders.customer_cancel.setting_hint') }}</p>
            </div>
            @if ($canEdit)<x-form-actions :cancel="route('admin.dashboard')" />@endif
        </form>
    </x-card>
</x-layouts.admin>
