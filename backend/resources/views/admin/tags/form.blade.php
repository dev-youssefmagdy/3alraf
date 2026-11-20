@php($editing = $tag->exists)
<x-layouts.admin :title="$editing ? __('admin.edit').': '.$tag->name : __('admin.add_new')">
    <x-slot:header><x-ui.page-header :title="$editing ? __('admin.edit').': '.$tag->name : __('admin.add_new')" /></x-slot:header>
    <x-ui.form-section :title="__('ui.sections.details')" :cols="1" class="mx-auto max-w-5xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('admin.tags.validate', $editing ? [$tag] : []) }}"
            action="{{ $editing ? route('admin.tags.update', $tag) : route('admin.tags.store') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <x-draft-notice :model="$tag" />
            <x-ui.lang-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$tag->getTranslations('name')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="slug" :label="__('admin.fields.slug_short')" :value="$tag->slug" :hint="__('admin.slug_hint')" />
                <x-ui.input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$tag->sort_order ?? 0" />
            </div>
            <x-ui.checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$tag->is_active" />
            <x-ui.save-bar :cancel="route('admin.tags.index')" :draftable="$tag" />
        </form>
    </x-ui.form-section>
</x-layouts.admin>
