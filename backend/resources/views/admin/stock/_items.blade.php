{{-- Items repeater. $rows = existing/old rows, $items = StockItemCatalog options, $cost = show unit cost --}}
@php($cost = $cost ?? false)
<div data-repeater id="items-repeater" class="space-y-2 border-t border-slate-100 pt-4">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-700">{{ __('ops.items_section') }}</h3>
        <button type="button" data-repeater-add class="btn-sm btn-secondary">+ {{ __('ops.add_item') }}</button>
    </div>
    <div data-repeater-list class="space-y-2">
        @foreach ($rows as $k => $r)
            @include('tenant.stock._item-row', ['i' => $k, 'r' => $r, 'items' => $items, 'cost' => $cost])
        @endforeach
    </div>
    <template data-repeater-template>@include('tenant.stock._item-row', ['i' => '__INDEX__', 'r' => null, 'items' => $items, 'cost' => $cost])</template>
</div>
