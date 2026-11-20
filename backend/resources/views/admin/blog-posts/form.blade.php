<x-resource-form :route="$route" :editing="$editing" :model="$model" width="max-w-4xl">
    <x-ui.input name="slug" :label="__('admin.fields.slug_short')" :value="$model->slug" :hint="__('growth.blog_slug_hint')" required />
    <x-ui.lang-tabs name="title" :label="__('growth.fields.title')" :languages="$languages" :values="$model->getTranslations('title')" />
    <x-ui.lang-tabs name="excerpt" type="textarea" :rows="3" :label="__('growth.fields.excerpt')" :languages="$languages" :values="$model->getTranslations('excerpt')" />
    <x-ui.lang-tabs name="content" type="textarea" :rows="14" :label="__('growth.fields.content')" :languages="$languages" :values="$model->getTranslations('content')" />
    <x-ui.lang-tabs name="meta_description" type="textarea" :rows="2" :label="__('growth.fields.meta_description')" :languages="$languages" :values="$model->getTranslations('meta_description')" />
    <x-ui.input name="published_at" type="datetime-local" :label="__('growth.fields.published_at')" :value="$model->published_at?->format('Y-m-d\TH:i')" />
    <x-ui.checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
</x-resource-form>
