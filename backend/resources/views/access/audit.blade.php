<x-layouts.admin :title="__('access.audit_log')">
    <x-card>
        <x-datatable :table="$table" :url="route('admin.audit.datatable')" id="audit-table" />
    </x-card>
</x-layouts.admin>
