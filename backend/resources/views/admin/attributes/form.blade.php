@php
    $editing = $attribute->exists;
    $rows = old('values', $editing ? $attribute->values->map(fn ($v) => ['id' => $v->id, 'value' => $v->getTranslations('value'), 'color_hex' => $v->color_hex])->all() : [['value' => []]]);
@endphp
<x-layouts.admin :title="$editing ? __('admin.edit').': '.$attribute->name : __('admin.add_new')">
    <x-slot:header><x-ui.page-header :title="$editing ? __('admin.edit').': '.$attribute->name : __('admin.add_new')" /></x-slot:header>
    <x-ui.form-section :title="__('ui.sections.details')" :cols="1" class="mx-auto max-w-5xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('admin.attributes.validate', $editing ? [$attribute] : []) }}"
            action="{{ $editing ? route('admin.attributes.update', $attribute) : route('admin.attributes.store') }}" method="POST" class="space-y-5" novalidate>
            @csrf
            <x-draft-notice :model="$attribute" />
            <x-ui.lang-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$attribute->getTranslations('name')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input name="code" :label="__('admin.fields.code')" :value="str_starts_with((string) $attribute->code, \App\Services\AttributeService::DRAFT_CODE_PREFIX) && $attribute->is_draft ? '' : $attribute->code" required />
                <x-ui.select name="type" :label="__('admin.fields.type')" :options="$types" :value="$attribute->type?->value" required data-attr-type />
                <x-ui.input name="sort_order" type="number" min="0" :label="__('admin.fields.sort_order')" :value="$attribute->sort_order ?? 0" />
            </div>

            <div data-repeater class="space-y-2" id="attr-values">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold">{{ __('admin.values') }}</h3>
                    <button type="button" data-repeater-add class="btn-sm btn-secondary">+ {{ __('admin.add_value') }}</button>
                </div>
                <div data-repeater-list class="space-y-2">
                    @foreach ($rows as $i => $row)
                        @include('admin.attributes._value-row', ['i' => $i, 'row' => $row, 'languages' => $languages])
                    @endforeach
                </div>
                <template data-repeater-template>
                    @include('admin.attributes._value-row', ['i' => '__INDEX__', 'row' => ['value' => []], 'languages' => $languages])
                </template>
                <p data-field-values class="field-error empty:hidden"></p>
            </div>
            <x-ui.save-bar :cancel="route('admin.attributes.index')" :draftable="$attribute" />
        </form>
    </x-ui.form-section>
</x-layouts.admin>
