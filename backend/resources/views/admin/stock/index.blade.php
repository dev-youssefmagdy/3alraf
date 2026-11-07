<x-layouts.admin guard="tenant" :title="__('ops.stock')">
    <div class="mb-4 grid gap-4 sm:grid-cols-2">
        <x-stat-card :label="__('ops.stock_value')" :value="number_format((float) $valuation, 2)" />
        <x-stat-card :label="__('ops.units_on_hand')" :value="number_format($units)" />
    </div>
    <x-card :title="__('ops.levels')">
        <x-slot:actions>
            <select name="branch_id" data-dt-filter="#stock-table" class="form-control !w-auto !py-1.5">
                <option value="">{{ __('ops.all_branches') }}</option>
                @foreach ($branches as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('tenant.stock.datatable')" id="stock-table" />
    </x-card>
</x-layouts.admin>
