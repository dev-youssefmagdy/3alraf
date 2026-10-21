<x-resource-form :route="$route" :editing="$editing" :model="$model" width="max-w-4xl">
    <x-input name="slug" :label="__('admin.fields.slug_short')" :value="$model->slug" :hint="__('growth.static_slug_hint')" required />
    <x-translatable-tabs name="title" :label="__('growth.fields.title')" :languages="$languages" :values="$model->getTranslations('title')" />
    <x-translatable-tabs name="content" type="textarea" :rows="12" :label="__('growth.fields.content')" :languages="$languages" :values="$model->getTranslations('content')" />
    <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
</x-resource-form>
