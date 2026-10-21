<x-layouts.admin guard="tenant" :title="__('growth.reviews')">
    <x-card>
        <div class="mb-4 max-w-xs">
            <x-select name="status" :label="__('admin.status')" :options="\App\Enums\ReviewStatus::options()" :placeholder="__('orders.all')" data-dt-filter="#reviews-table" />
        </div>
        <x-datatable :table="$table" :url="route('tenant.reviews.datatable')" id="reviews-table" />
    </x-card>
</x-layouts.admin>
