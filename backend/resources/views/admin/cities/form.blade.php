<x-resource-form :route="$route" :editing="$editing" :model="$model">
    <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$model->getTranslations('name')" />
    <x-input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$model->sort_order ?? 0" />
    <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
</x-resource-form>
