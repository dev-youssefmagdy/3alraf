<x-layouts.admin :title="__('ops.cod_fee_title')">
    <x-slot:header><x-ui.page-header :title="__('ops.cod_fee_title')" /></x-slot:header>
    <x-ui.form-section :title="__('ui.sections.details')" :cols="1" class="mx-auto max-w-5xl">
        <form data-ajax-form data-method="PUT" data-validate-url="{{ route('admin.cod-fees.validate') }}" action="{{ route('admin.cod-fees.update') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <p class="text-sm text-panel-text-muted">{{ __('ops.cod_fee_scope') }} {{ __('ops.cod_fee_hint') }}</p>
            <x-ui.checkbox name="cod_extra_fee_enabled" :label="__('ops.fields.cod_extra_fee_enabled')" :checked="$cfg->enabled" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.select name="cod_extra_fee_type" :label="__('ops.fields.cod_extra_fee_type')" :options="__('ops.cod_fee_types')" :value="$cfg->type" />
                <x-ui.input name="cod_extra_fee_fixed" type="number" step="0.01" min="0" :label="__('ops.fields.cod_extra_fee_fixed')" :value="$cfg->fixed" />
                <x-ui.input name="cod_extra_fee_percent" type="number" step="0.001" min="0" max="100" :label="__('ops.fields.cod_extra_fee_percent')" :value="$cfg->percent" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input name="cod_extra_fee_min" type="number" step="0.01" min="0" :label="__('ops.fields.cod_extra_fee_min')" :value="$cfg->min" />
                <x-ui.input name="cod_extra_fee_max" type="number" step="0.01" min="0" :label="__('ops.fields.cod_extra_fee_max')" :value="$cfg->max" />
                <x-ui.input name="cod_extra_fee_free_over" type="number" step="0.01" min="0" :label="__('ops.fields.cod_extra_fee_free_over')" :value="$cfg->freeOver" />
            </div>
            <x-ui.select name="cod_extra_fee_bearer" :label="__('ops.fields.cod_extra_fee_bearer')" :options="__('ops.cod_fee_bearers')" :value="$cfg->bearer" />
            <x-ui.lang-tabs name="cod_extra_fee_label" :label="__('ops.fields.cod_extra_fee_label')" :languages="$languages" :values="$cfg->label" />
            @if ($cities->isNotEmpty())
                <div class="space-y-2 border-t border-panel-border pt-4">
                    <h3 class="text-sm font-semibold text-panel-text">{{ __('ops.fields.cod_extra_fee_city_fixed') }}</h3>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ($cities as $city)
                            <x-ui.input name="cod_extra_fee_city_fixed[{{ $city->id }}]" type="number" step="0.01" min="0" :label="$city->name" :value="$cfg->cityFixed[$city->id] ?? null" />
                        @endforeach
                    </div>
                </div>
            @endif
            <x-ui.save-bar :cancel="route('admin.dashboard')" />
        </form>
    </x-ui.form-section>
</x-layouts.admin>
