@php
    $panel = request()->routeIs('marketer.*') ? 'marketer' : 'admin';
@endphp
<x-layouts.panel :title="__('growth.affiliates')">
    <x-card>
        <x-datatable :table="$table" :url="route($panel.'.affiliates.datatable')" id="affiliates-table" />
    </x-card>
</x-layouts.panel>
