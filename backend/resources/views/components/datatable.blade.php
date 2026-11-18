{{-- $table = App\DataTables\BaseDataTable ; $url = ajax endpoint. The table's filters() become a filter bar above it (wired in lib/ops-form.js). --}}
@props(['table', 'url', 'id' => 'dt'])
@php($filters = $table->filters())
@if ($filters)
    <div data-dt-filters class="mb-4 grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
        @foreach ($filters as $filter)
            @php($fieldId = $id.'-filter-'.$filter->name)
            <div>
                <label for="{{ $fieldId }}" class="form-label">{{ $filter->label }}</label>
                @if ($filter->type === 'select')
                    <select id="{{ $fieldId }}" name="{{ $filter->name }}" data-dt-filter="#{{ $id }}" @if ($filter->searchable) data-search @endif class="form-control">
                        <option value="">{{ __('admin.table_filters.all') }}</option>
                        @foreach ($filter->options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                @else
                    <input id="{{ $fieldId }}" type="{{ $filter->type }}" name="{{ $filter->name }}" data-dt-filter="#{{ $id }}" autocomplete="off" class="form-control">
                @endif
            </div>
        @endforeach
        <div><button type="button" data-dt-filter-reset="#{{ $id }}" class="btn-sm btn-secondary">{{ __('admin.table_filters.reset') }}</button></div>
    </div>
@endif
<div class="min-w-0">
    <table id="{{ $id }}" data-datatable data-url="{{ $url }}" data-columns='@json($table->clientColumns())' class="display w-full"></table>
</div>
