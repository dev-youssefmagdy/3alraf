@php
    $panel = request()->routeIs('marketer.*') ? 'marketer' : 'admin';
@endphp
<x-layouts.panel :title="__('growth.reviews')">
    <x-slot:header><x-ui.page-header :title="__('growth.reviews')" /></x-slot:header>
        <div class="space-y-4">
        <div class="mb-4 max-w-xs">
            <x-ui.select name="status" :label="__('admin.status')" :options="\App\Enums\ReviewStatus::options()" :placeholder="__('orders.all')" data-dt-filter="#reviews-table" />
        </div>
        <x-ui.data-table :table="$table" :url="route($panel.'.reviews.datatable')" id="reviews-table" />
        </div>
</x-layouts.panel>
