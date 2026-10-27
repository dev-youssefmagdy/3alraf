<x-layouts.admin guard="tenant" :title="__('growth_hub.settings.title')">
    <x-card class="max-w-3xl">
        <p class="mb-4 text-sm text-slate-600">{{ __('growth_hub.settings.hint') }}</p>
        <form data-ajax-form data-method="PUT" action="{{ route('tenant.growth.settings.update') }}" method="POST" class="grid gap-4 sm:grid-cols-2" novalidate>
            @csrf
            @foreach ($limits as $k => [$min, $max])
                <x-input name="{{ $k }}" type="number" step="any" :min="$min" :max="$max" :label="__('growth_hub.settings.fields.'.$k)" :value="$overrides[$k] ?? null"
                    :hint="__('growth_hub.settings.default', ['v' => $defaults[$k]])" />
            @endforeach
            <x-input name="auto_reply_allowlist" class="sm:col-span-2" :label="__('growth_hub.settings.fields.auto_reply_allowlist')" :value="implode(', ', $overrides['auto_reply_allowlist'] ?? [])" :hint="__('growth_hub.settings.allowlist_hint')" />
            <div class="sm:col-span-2"><x-button type="submit">{{ __('admin.save') }}</x-button></div>
        </form>
    </x-card>
</x-layouts.admin>
