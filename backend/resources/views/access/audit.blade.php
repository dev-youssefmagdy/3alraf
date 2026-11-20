<x-layouts.admin :title="__('access.audit_log')">
    <x-slot:header><x-ui.page-header :title="__('access.audit_log')" /></x-slot:header>
    <x-ui.data-table :table="$table" :url="route('admin.audit.datatable')" id="audit-table" />
</x-layouts.admin>
