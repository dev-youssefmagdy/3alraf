@php($canEdit = auth('admin')->user()?->can('general_settings.edit'))
<x-layouts.admin :title="__('admin.settings_general')">
    <x-slot:header><x-ui.page-header :title="__('admin.settings_general')" /></x-slot:header>
            <form data-ajax-form data-method="PUT" data-validate-url="{{ route('admin.general-settings.validate') }}"
            action="{{ route('admin.general-settings.update') }}" method="POST" class="mx-auto max-w-5xl space-y-5" novalidate>
            @csrf
            <x-ui.form-section :title="__('ui.sections.basic')">
                <x-ui.input name="name" :label="__('admin.fields.name')" :value="$s['name']" required />
                <x-ui.input name="country" :label="__('admin.fields.country')" :value="$s['country']" maxlength="2" />
                <x-ui.input name="timezone" :label="__('admin.fields.timezone')" :value="$s['timezone']" required />
                <x-ui.input name="currency_code" :label="__('admin.fields.currency_code')" :value="$s['currency_code']" required maxlength="3" />
                <x-ui.input name="currency_symbol" :label="__('admin.fields.currency_symbol')" :value="$s['currency_symbol']" required />
                <x-ui.select name="default_locale" :label="__('admin.fields.default_locale')" :options="$languages->pluck('native_name', 'code')->all()" :value="$s['default_locale']" required />
                <div data-field data-span-all>
                <div class="form-label">{{ __('admin.fields.locales') }}</div>
                <div class="flex flex-wrap gap-4">
                    @foreach ($languages as $l)
                        <x-ui.checkbox name="locales[]" :value="$l->code" :bool="false" :label="$l->native_name" :checked="in_array($l->code, old('locales', $s['locales']))" />
                    @endforeach
                </div>
                </div>
            </x-ui.form-section>
            <x-ui.form-section :title="__('ui.sections.status')" :cols="1">
            <div class="space-y-2 rounded-lg border border-dashed border-panel-border-strong p-4">
                <x-ui.checkbox name="gift_finder_enabled" :label="__('gift_finder.setting')" :checked="$s['gift_finder_enabled']" />
                <p class="text-xs text-panel-text-subtle">{{ __('gift_finder.setting_hint') }}</p>
            </div>
            <div class="space-y-2 rounded-lg border border-dashed border-panel-border-strong p-4">
                <x-ui.checkbox name="customer_cancel_enabled" :label="__('orders.customer_cancel.setting')" :checked="$s['customer_cancel_enabled']" />
                <p class="text-xs text-panel-text-subtle">{{ __('orders.customer_cancel.setting_hint') }}</p>
            </div>
            </x-ui.form-section>
            <x-ui.form-section :title="__('ui.sections.programs')" :cols="1">
            {{-- affiliate sales bonus (docs/CALCULATIONS.md §Affiliate sales bonus); the launch date is shown only, never submitted --}}
            <fieldset class="space-y-3 rounded-lg border border-dashed border-panel-border-strong p-4">
                <legend class="px-1 text-sm font-semibold text-panel-text">{{ __('admin.affiliate_bonus_section') }}</legend>
                <x-ui.checkbox name="affiliate_bonus_enabled" :label="__('admin.fields.affiliate_bonus_enabled')" :checked="$s['affiliate_bonus_enabled']" />
                <p class="text-xs text-panel-text-subtle">{{ __('admin.affiliate_bonus_intro') }}</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><x-ui.input name="affiliate_bonus_threshold" type="number" min="1000" step="0.01" inputmode="decimal" :label="__('admin.fields.affiliate_bonus_threshold').' ('.$s['currency_code'].')'" :value="$s['affiliate_bonus_threshold']" required /></div>
                    <div><x-ui.input name="affiliate_bonus_amount" type="number" min="0.01" step="0.01" inputmode="decimal" :label="__('admin.fields.affiliate_bonus_amount').' ('.$s['currency_code'].')'" :value="$s['affiliate_bonus_amount']" required /></div>
                </div>
                <div>
                    <label for="f-affiliate_bonus_started_at" class="form-label">{{ __('admin.fields.affiliate_bonus_started_at') }}</label>
                    <input id="f-affiliate_bonus_started_at" type="text" class="form-control bg-panel-surface-sunken text-panel-text-muted" dir="ltr" readonly aria-describedby="h-affiliate_bonus_started_at"
                        value="{{ $s['affiliate_bonus_started_at'] ? \Illuminate\Support\Carbon::parse($s['affiliate_bonus_started_at'])->format('Y-m-d H:i') : __('admin.affiliate_bonus_not_started') }}">
                    <p id="h-affiliate_bonus_started_at" class="mt-1 text-xs text-panel-text-subtle">{{ __('admin.affiliate_bonus_started_hint') }}</p>
                </div>
            </fieldset>
            </x-ui.form-section>
            @if ($canEdit)<x-ui.save-bar :cancel="route('admin.dashboard')" />@endif
        </form>
</x-layouts.admin>
