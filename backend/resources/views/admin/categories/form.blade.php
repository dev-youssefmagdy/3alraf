@php($editing = $category->exists)
<x-layouts.admin guard="central" :title="$editing ? __('admin.edit').': '.$category->name : __('admin.add_new')">
    <x-card class="max-w-3xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('central.categories.validate', $editing ? [$category] : []) }}"
            action="{{ $editing ? route('central.categories.update', $category) : route('central.categories.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4" novalidate>
            @csrf
            <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$category->getTranslations('name')" />
            <x-translatable-tabs name="description" type="textarea" :label="__('admin.fields.description')" :languages="$languages" :values="$category->getTranslations('description')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="parent_id" :label="__('admin.fields.parent_category')" :options="$parents" :value="$category->parent_id" :placeholder="__('admin.root_category')" />
                <x-input name="slug" :label="__('admin.fields.slug_short')" :value="$category->slug" :hint="__('admin.slug_hint')" />
                <x-input name="icon" :label="__('admin.fields.icon')" :value="$category->icon" />
                <x-input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$category->sort_order ?? 0" />
            </div>
            <div data-field>
                <label class="form-label" for="f-image">{{ __('admin.fields.image') }}</label>
                @if ($category->image_url)
                    <img src="{{ $category->image_url }}" class="mb-2 size-20 rounded border object-cover" alt="">
                    <x-checkbox name="remove_image" :label="__('admin.remove_image')" />
                @endif
                <input id="f-image" type="file" name="image" accept="image/*" class="form-control">
            </div>
            <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$category->is_active" />
            <x-form-actions :cancel="route('central.categories.index')" />
        </form>
    </x-card>
</x-layouts.admin>
