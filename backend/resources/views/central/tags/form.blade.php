@php($editing = $tag->exists)
<x-layouts.admin guard="central" :title="$editing ? __('admin.edit').': '.$tag->name : __('admin.add_new')">
    <x-card class="max-w-2xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('central.tags.validate', $editing ? [$tag] : []) }}"
            action="{{ $editing ? route('central.tags.update', $tag) : route('central.tags.store') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$tag->getTranslations('name')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="slug" :label="__('admin.fields.slug_short')" :value="$tag->slug" :hint="__('admin.slug_hint')" />
                <x-input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$tag->sort_order ?? 0" />
            </div>
            <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$tag->is_active" />
            <x-form-actions :cancel="route('central.tags.index')" />
        </form>
    </x-card>
</x-layouts.admin>
