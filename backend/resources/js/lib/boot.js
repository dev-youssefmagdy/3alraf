import $ from 'jquery';
import DataTable from 'datatables.net-dt';
import './boot-core';

// Must run before any other module: they may fire AJAX / use DataTables during evaluation.
// DataTables 3 is jQuery-optional, so attach it to our jQuery instance ($.fn.DataTable / $.fn.dataTable).
DataTable.use($);
window.DataTable = DataTable;
