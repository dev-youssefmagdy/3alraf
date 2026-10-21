import $ from 'jquery';
import DataTable from 'datatables.net-dt';

// Must run before any other module: they may fire AJAX / use DataTables during evaluation.
// DataTables 3 is jQuery-optional, so attach it to our jQuery instance ($.fn.DataTable / $.fn.dataTable).
DataTable.use($);
window.DataTable = DataTable;

$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), Accept: 'application/json' } });
