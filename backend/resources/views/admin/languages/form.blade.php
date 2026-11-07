@php($editing = $language->exists)
<x-layouts.admin guard="central" :title="$editing ? __('admin.edit').': '.$language->name : __('admin.add_new')">
    <x-card class="max-w-2xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('central.languages.validate', $editing ? [$language] : []) }}"
            action="{{ $editing ? route('central.languages.update', $language) : route('central.languages.store') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="code" :label="__('admin.fields.code')" :value="$language->code" required maxlength="10" />
                <x-input name="sort_order" type="number" :label="__('admin.fields.sort_order')" :value="$language->sort_order ?? 0" min="0" />
                <x-input name="name" :label="__('admin.fields.name')" :value="$language->name" required />
                <x-input name="native_name" :label="__('admin.fields.native_name')" :value="$language->native_name" required />
                <x-select name="direction" :label="__('admin.fields.direction')" :options="$directions" :value="$language->direction?->value" required />
            </div>
            <div class="flex flex-wrap gap-6">
                <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$language->is_active" />
                <x-checkbox name="is_default" :label="__('admin.fields.is_default')" :checked="$language->is_default" />
            </div>
            <x-form-actions :cancel="route('central.languages.index')" />
        </form>
    </x-card>
</x-layouts.admin>
