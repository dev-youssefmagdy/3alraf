<x-layouts.admin :guard="$guard" :title="__('access.audit_log')">
    <x-card>
        <x-datatable :table="$table" :url="route($guard.'.audit.datatable')" id="audit-table" />
    </x-card>
</x-layouts.admin>
