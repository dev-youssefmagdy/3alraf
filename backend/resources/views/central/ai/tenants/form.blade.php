@php($allowed = $setting->allowed_model_ids)
<x-layouts.admin guard="central" :title="__('ai.menu.tenants').': '.$tenant->name">
    <x-card class="max-w-3xl">
        <form data-ajax-form data-method="PUT" data-validate-url="{{ route('central.ai.tenants.validate', $tenant) }}"
            action="{{ route('central.ai.tenants.update', $tenant) }}" method="POST" class="space-y-5" novalidate>
            @csrf
            <p class="text-sm text-slate-600">{{ __('ai.usage_this_month') }}: {{ number_format($usage['tokens']) }} {{ __('ai.tokens') }}, {{ $usage['images'] }} {{ __('ai.images') }}, {{ $usage['video'] }} {{ __('ai.video_secs') }}</p>
            <x-checkbox name="ai_enabled" :label="__('ai.fields.ai_enabled')" :checked="$setting->ai_enabled" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-input name="monthly_token_quota" type="number" min="0" :label="__('ai.fields.monthly_token_quota')" :value="$setting->monthly_token_quota" :hint="__('ai.quota_hint')" />
                <x-input name="monthly_image_quota" type="number" min="0" :label="__('ai.fields.monthly_image_quota')" :value="$setting->monthly_image_quota" />
                <x-input name="monthly_video_quota" type="number" min="0" :label="__('ai.fields.monthly_video_quota')" :value="$setting->monthly_video_quota" />
            </div>
            <div>
                <p class="form-label">{{ __('ai.fields.features') }}</p>
                <div class="flex flex-wrap gap-4">
                    @foreach (\App\Enums\AiFeature::cases() as $f)
                        <x-checkbox name="features[]" :bool="false" :value="$f->value" :label="$f->label()" :checked="($setting->features[$f->value] ?? false)" />
                    @endforeach
                </div>
            </div>
            <div>
                <p class="form-label">{{ __('ai.fields.allowed_models') }}</p>
                <p class="mb-2 text-xs text-slate-500">{{ __('ai.allowed_hint') }}</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($models as $m)
                        <x-checkbox name="allowed_model_ids[]" :bool="false" :value="$m->id" :label="$m->displayName().' ('.$m->kind->label().')'" :checked="$allowed !== null && in_array($m->id, $allowed)" />
                    @endforeach
                </div>
            </div>
            <x-form-actions :cancel="route('central.ai.tenants.index')" />
        </form>
    </x-card>
</x-layouts.admin>
