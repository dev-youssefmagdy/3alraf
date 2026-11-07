<x-layouts.admin guard="tenant" :title="__('ops.stock_movements')">
    <x-card :title="__('ops.ledger')">
        <x-slot:actions>
            <select name="branch_id" data-dt-filter="#movements-table" class="form-control !w-auto !py-1.5">
                <option value="">{{ __('ops.all_branches') }}</option>
                @foreach ($branches as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select name="type" data-dt-filter="#movements-table" class="form-control !w-auto !py-1.5">
                <option value="">{{ __('ops.all_types') }}</option>
                @foreach ($types as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
            </select>
        </x-slot:actions>
        <x-datatable :table="$table" :url="route('tenant.stock.movements.datatable')" id="movements-table" />
    </x-card>
</x-layouts.admin>
