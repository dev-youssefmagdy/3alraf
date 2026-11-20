@php($editing = $brand->exists)
<x-layouts.admin :title="$editing ? __('admin.edit').': '.$brand->name : __('admin.add_new')">
    <x-slot:header><x-ui.page-header :title="$editing ? __('admin.edit').': '.$brand->name : __('admin.add_new')" /></x-slot:header>
    <x-ui.form-section :title="__('ui.sections.details')" :cols="1" class="mx-auto max-w-5xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('admin.brands.validate', $editing ? [$brand] : []) }}"
            action="{{ $editing ? route('admin.brands.update', $brand) : route('admin.brands.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4" novalidate>
            @csrf
            <x-draft-notice :model="$brand" />
            <x-ui.lang-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$brand->getTranslations('name')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="slug" :label="__('admin.fields.slug_short')" :value="$brand->slug" :hint="__('admin.slug_hint')" />
                <x-ui.input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$brand->sort_order ?? 0" />
            </div>
            <div data-field>
                <label class="form-label" for="f-logo">{{ __('admin.fields.logo') }}</label>
                @if ($brand->logo_url)
                    <img src="{{ $brand->logo_url }}" class="mb-2 size-16 rounded border object-contain" alt="">
                    <x-ui.checkbox name="remove_logo" :label="__('admin.remove_image')" />
                @endif
                <input id="f-logo" type="file" name="logo" accept="image/*" class="form-control">
            </div>
            <x-ui.checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$brand->is_active" />
            <x-ui.save-bar :cancel="route('admin.brands.index')" :draftable="$brand" />
        </form>
    </x-ui.form-section>
</x-layouts.admin>
