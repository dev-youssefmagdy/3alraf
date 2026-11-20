<x-layouts.admin :title="__('ops.stock')">
    <x-slot:header><x-ui.page-header :title="__('ops.stock')"><select name="branch_id" data-dt-filter="#stock-table" class="form-control !w-auto !py-1.5">
                <option value="">{{ __('ops.all_branches') }}</option>
                @foreach ($branches as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select></x-ui.page-header></x-slot:header>
    <div class="mb-4 grid gap-4 sm:grid-cols-2">
        <x-ui.stat-card :label="__('ops.stock_value')" :value="number_format((float) $valuation, 2)" />
        <x-ui.stat-card :label="__('ops.units_on_hand')" :value="number_format($units)" />
    </div>
        <div class="space-y-4">
        <h2 class="text-panel-h4 font-bold text-panel-text">{{ __('ops.levels') }}</h2>
        <x-ui.data-table :table="$table" :url="route('admin.stock.datatable')" id="stock-table" />
        </div>
</x-layouts.admin>
