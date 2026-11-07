<x-resource-form :route="$route" :editing="$editing" :model="$model" width="max-w-4xl">
    <x-input name="slug" :label="__('admin.fields.slug_short')" :value="$model->slug" :hint="__('growth.blog_slug_hint')" required />
    <x-translatable-tabs name="title" :label="__('growth.fields.title')" :languages="$languages" :values="$model->getTranslations('title')" />
    <x-translatable-tabs name="excerpt" type="textarea" :rows="3" :label="__('growth.fields.excerpt')" :languages="$languages" :values="$model->getTranslations('excerpt')" />
    <x-translatable-tabs name="content" type="textarea" :rows="14" :label="__('growth.fields.content')" :languages="$languages" :values="$model->getTranslations('content')" />
    <x-translatable-tabs name="meta_description" type="textarea" :rows="2" :label="__('growth.fields.meta_description')" :languages="$languages" :values="$model->getTranslations('meta_description')" />
    <x-input name="published_at" type="datetime-local" :label="__('growth.fields.published_at')" :value="$model->published_at?->format('Y-m-d\TH:i')" />
    <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
</x-resource-form>
