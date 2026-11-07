@php($editing = $provider->exists)
<x-layouts.admin guard="central" :title="$editing ? __('admin.edit').': '.$provider->name : __('admin.add_new')">
    <x-card class="max-w-2xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('central.ai.providers.validate', $editing ? [$provider] : []) }}"
            action="{{ $editing ? route('central.ai.providers.update', $provider) : route('central.ai.providers.store') }}" method="POST" class="space-y-4" novalidate autocomplete="off">
            @csrf
            <x-select name="driver" required :label="__('ai.fields.driver')" :options="\App\Enums\AiDriverType::options()" :value="$provider->driver?->value" />
            <x-input name="name" required :label="__('ai.fields.name')" :value="$provider->name" />
            {{-- the stored key is never sent back to the browser: only a masked tail is shown --}}
            <x-input name="api_key" type="password" autocomplete="new-password" :required="! $editing" :label="__('ai.fields.api_key')" :hint="$editing ? __('ai.key_keep').' '.$provider->maskedKey() : null" />
            <x-input name="base_url" :label="__('ai.fields.base_url')" :value="$provider->base_url" :hint="__('ai.base_url_hint')" />
            <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$provider->is_active" />
            <x-form-actions :cancel="route('central.ai.providers.index')" />
        </form>
    </x-card>
</x-layouts.admin>
