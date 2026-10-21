<x-resource-form :route="$route" :editing="$editing" :model="$model">
    <x-input name="name" :label="__('admin.fields.name')" :value="$model->name" required />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-input name="min_sales" type="number" step="0.01" min="0" :label="__('growth.fields.min_sales')" :value="$model->min_sales" :hint="__('growth.min_sales_hint')" required />
        <x-input name="percent" type="number" step="0.01" min="0" max="100" :label="__('growth.fields.percent')" :value="$model->percent" required />
    </div>
</x-resource-form>
