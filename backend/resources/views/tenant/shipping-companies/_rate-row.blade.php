{{-- $i = row index (or __INDEX__), $r = rate array|null, $cities --}}
<div data-repeater-item class="grid items-end gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-6">
    <x-select name="rates[{{ $i }}][city_id]" :label="__('ops.fields.city')" :options="$cities" :value="$r['city_id'] ?? null" :placeholder="__('admin.select')" class="sm:col-span-2" />
    <x-input name="rates[{{ $i }}][price]" type="number" step="0.01" min="0" :label="__('ops.fields.price')" :value="$r['price'] ?? null" />
    <x-input name="rates[{{ $i }}][cost]" type="number" step="0.01" min="0" :label="__('ops.fields.carrier_cost')" :value="$r['cost'] ?? null" />
    <x-input name="rates[{{ $i }}][free_over]" type="number" step="0.01" min="0" :label="__('ops.fields.free_over')" :value="$r['free_over'] ?? null" />
    <div class="flex items-end gap-2">
        <x-input name="rates[{{ $i }}][eta_min_days]" type="number" min="0" :label="__('ops.fields.eta_min')" :value="$r['eta_min_days'] ?? null" />
        <x-input name="rates[{{ $i }}][eta_max_days]" type="number" min="0" :label="__('ops.fields.eta_max')" :value="$r['eta_max_days'] ?? null" />
    </div>
    <div class="flex items-center justify-between sm:col-span-6">
        <x-checkbox name="rates[{{ $i }}][supports_cod]" :label="__('ops.fields.supports_cod')" :checked="$r['supports_cod'] ?? true" />
        <button type="button" data-repeater-remove class="btn-sm btn-danger">&times;</button>
    </div>
</div>
