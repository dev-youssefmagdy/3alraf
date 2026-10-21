<x-resource-form :route="$route" :editing="$editing" :model="$model">
    <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$model->getTranslations('name')" />
    <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
</x-resource-form>
