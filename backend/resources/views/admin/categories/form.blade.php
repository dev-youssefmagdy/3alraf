@php($editing = $category->exists)
<x-layouts.admin :title="$editing ? __('admin.edit').': '.$category->name : __('admin.add_new')">
    <x-slot:header><x-ui.page-header :title="$editing ? __('admin.edit').': '.$category->name : __('admin.add_new')" /></x-slot:header>
            <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('admin.categories.validate', $editing ? [$category] : []) }}"
            action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" method="POST" enctype="multipart/form-data" class="mx-auto max-w-5xl space-y-5" novalidate>
            @csrf
            <x-draft-notice :model="$category" />
            <x-ui.form-section :title="__('ui.sections.basic')" :cols="1">
            <x-ui.lang-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$category->getTranslations('name')" />
            <x-ui.lang-tabs name="description" type="textarea" :label="__('admin.fields.description')" :languages="$languages" :values="$category->getTranslations('description')" />
            </x-ui.form-section>
            <x-ui.form-section :title="__('ui.sections.organization')">
                <x-ui.select name="parent_id" :label="__('admin.fields.parent_category')" :options="$parents" :value="$category->parent_id" :placeholder="__('admin.root_category')" />
                <x-ui.input name="slug" :label="__('admin.fields.slug_short')" :value="$category->slug" :hint="__('admin.slug_hint')" />
                <x-ui.input name="icon" :label="__('admin.fields.icon')" :value="$category->icon" />
                <x-ui.input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$category->sort_order ?? 0" />
            </x-ui.form-section>
            <x-ui.form-section :title="__('ui.sections.media')" :cols="1">
            <div data-field>
                <label class="form-label" for="f-image">{{ __('admin.fields.image') }}</label>
                @if ($category->image_url)
                    <img src="{{ $category->image_url }}" class="mb-2 size-20 rounded border object-cover" alt="">
                    <x-ui.checkbox name="remove_image" :label="__('admin.remove_image')" />
                @endif
                <input id="f-image" type="file" name="image" accept="image/*" class="form-control">
            </div>
            </x-ui.form-section>
            <x-ui.form-section :title="__('ui.sections.status')" :cols="1">
            <x-ui.checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$category->is_active" />
            </x-ui.form-section>
            <x-ui.save-bar :cancel="route('admin.categories.index')" :draftable="$category" />
        </form>
</x-layouts.admin>
