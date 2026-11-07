@php($editing = $brand->exists)
<x-layouts.admin guard="central" :title="$editing ? __('admin.edit').': '.$brand->name : __('admin.add_new')">
    <x-card class="max-w-2xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('central.brands.validate', $editing ? [$brand] : []) }}"
            action="{{ $editing ? route('central.brands.update', $brand) : route('central.brands.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4" novalidate>
            @csrf
            <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$brand->getTranslations('name')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="slug" :label="__('admin.fields.slug_short')" :value="$brand->slug" :hint="__('admin.slug_hint')" />
                <x-input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$brand->sort_order ?? 0" />
            </div>
            <div data-field>
                <label class="form-label" for="f-logo">{{ __('admin.fields.logo') }}</label>
                @if ($brand->logo_url)
                    <img src="{{ $brand->logo_url }}" class="mb-2 size-16 rounded border object-contain" alt="">
                    <x-checkbox name="remove_logo" :label="__('admin.remove_image')" />
                @endif
                <input id="f-logo" type="file" name="logo" accept="image/*" class="form-control">
            </div>
            <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$brand->is_active" />
            <x-form-actions :cancel="route('central.brands.index')" />
        </form>
    </x-card>
</x-layouts.admin>
