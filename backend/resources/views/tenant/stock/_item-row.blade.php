{{-- $i index|__INDEX__, $r = ['item','quantity','unit_cost']|null, $items options, $cost bool, $costRequired bool --}}
<div data-repeater-item data-field class="grid items-end gap-2 rounded-lg border border-slate-200 p-3 {{ $cost ? 'sm:grid-cols-12' : 'sm:grid-cols-8' }}">
    <div data-field class="{{ $cost ? 'sm:col-span-6' : 'sm:col-span-6' }}">
        <label class="form-label">{{ __('ops.fields.item') }}</label>
        <select name="items[{{ $i }}][item]" data-item-select class="form-control">
            <option value="">{{ __('ops.select_item') }}</option>
            @foreach ($items as $o)
                <option value="{{ $o['key'] }}" data-cost="{{ $o['cost'] }}" @selected(($r['item'] ?? null) === $o['key'])>{{ $o['label'] }}@if ($o['sku']) ({{ $o['sku'] }})@endif</option>
            @endforeach
        </select>
    </div>
    <x-input name="items[{{ $i }}][quantity]" type="number" min="1" step="1" :label="__('ops.fields.quantity')" :value="$r['quantity'] ?? 1" class="sm:col-span-2" />
    @if ($cost)
        <x-input name="items[{{ $i }}][unit_cost]" type="number" min="0" step="0.01" :label="__('ops.fields.unit_cost')" :value="$r['unit_cost'] ?? null" data-item-cost class="sm:col-span-3" />
    @endif
    <div class="flex justify-end"><button type="button" data-repeater-remove class="btn-sm btn-danger">&times;</button></div>
</div>
