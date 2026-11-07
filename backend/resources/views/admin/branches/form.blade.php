<x-resource-form :route="$route" :editing="$editing" :model="$model">
    <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$model->getTranslations('name')" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select name="city_id" :label="__('ops.fields.city')" :options="$cities" :value="$model->city_id" :placeholder="__('admin.select')" />
        <x-input name="phone" :label="__('ops.fields.phone')" :value="$model->phone" />
    </div>
    <x-input name="address" :label="__('ops.fields.address')" :value="$model->address" />
    <div class="flex gap-6">
        <x-checkbox name="is_default" :label="__('ops.fields.is_default')" :checked="$model->is_default" />
        <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
    </div>
</x-resource-form>
