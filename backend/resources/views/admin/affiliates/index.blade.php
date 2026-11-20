@php
    $panel = request()->routeIs('marketer.*') ? 'marketer' : 'admin';
@endphp
<x-layouts.panel :title="__('growth.affiliates')">
    <x-slot:header><x-ui.page-header :title="__('growth.affiliates')" /></x-slot:header>
    <x-ui.data-table :table="$table" :url="route($panel.'.affiliates.datatable')" id="affiliates-table" />
</x-layouts.panel>
