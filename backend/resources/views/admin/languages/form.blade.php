@php($editing = $language->exists)
<x-layouts.admin :title="$editing ? __('admin.edit').': '.$language->name : __('admin.add_new')">
    <x-slot:header><x-ui.page-header :title="$editing ? __('admin.edit').': '.$language->name : __('admin.add_new')" /></x-slot:header>
            <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('admin.languages.validate', $editing ? [$language] : []) }}"
            action="{{ $editing ? route('admin.languages.update', $language) : route('admin.languages.store') }}" method="POST" class="mx-auto max-w-5xl space-y-5" novalidate>
            @csrf
            <x-ui.form-section :title="__('ui.sections.localization')">
                <x-ui.input name="code" :label="__('admin.fields.code')" :value="$language->code" required maxlength="10" />
                <x-ui.input name="sort_order" type="number" :label="__('admin.fields.sort_order')" :value="$language->sort_order ?? 0" min="0" />
                <x-ui.input name="name" :label="__('admin.fields.name')" :value="$language->name" required />
                <x-ui.input name="native_name" :label="__('admin.fields.native_name')" :value="$language->native_name" required />
                <x-ui.select name="direction" :label="__('admin.fields.direction')" :options="$directions" :value="$language->direction?->value" required />
            </x-ui.form-section>
            <x-ui.form-section :title="__('ui.sections.status')" :cols="1">
            <div class="flex flex-wrap gap-6">
                <x-ui.checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$language->is_active" />
                <x-ui.checkbox name="is_default" :label="__('admin.fields.is_default')" :checked="$language->is_default" />
            </div>
            </x-ui.form-section>
            <x-ui.save-bar :cancel="route('admin.languages.index')" />
        </form>
</x-layouts.admin>
