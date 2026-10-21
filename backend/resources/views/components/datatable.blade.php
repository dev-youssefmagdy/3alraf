{{-- $table = App\DataTables\BaseDataTable ; $url = ajax endpoint --}}
@props(['table', 'url', 'id' => 'dt'])
<div class="overflow-x-auto">
    <table id="{{ $id }}" data-datatable data-url="{{ $url }}" data-columns='@json($table->columns())' class="display w-full"></table>
</div>
