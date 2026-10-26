<x-layouts.admin guard="tenant" :title="__('ai.menu.settings')">
    <x-card class="max-w-3xl">
        @php($quota = $setting->monthly_token_quota)
        <div class="mb-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
            {{ __('ai.usage_this_month') }}: {{ number_format($usage['tokens']) }} / {{ $quota === null ? __('ai.unlimited') : number_format($quota) }} {{ __('ai.tokens') }}
            @if ($quota)<div class="mt-2 h-2 rounded bg-slate-200"><div class="h-2 rounded bg-brand-600" style="width: {{ min(100, round($usage['tokens'] / $quota * 100)) }}%"></div></div>@endif
        </div>
        <form data-ajax-form data-method="PUT" data-validate-url="{{ route('tenant.ai.settings.validate') }}" action="{{ route('tenant.ai.settings.update') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <x-select name="default_ai_model_id" search :label="__('ai.fields.default_model')" :placeholder="__('ai.platform_default')" :options="$models->pluck('label', 'id')->all()" :value="$setting->default_ai_model_id" />
            <x-textarea name="brand_voice" rows="4" :label="__('ai.fields.brand_voice')" :value="$setting->brand_voice" />
            <x-textarea name="forbidden_words" rows="2" :label="__('ai.fields.forbidden_words')" :value="implode(', ', $setting->forbidden_words ?? [])" />
            <div>
                <p class="form-label">{{ __('ai.fields.default_platforms') }}</p>
                <div class="flex flex-wrap gap-4">
                    @foreach (\App\Http\Requests\Tenant\AiSettingsRequest::PLATFORMS as $p)
                        <x-checkbox name="default_platforms[]" :bool="false" :value="$p" :label="__('ai.platforms.'.$p)" :checked="in_array($p, $setting->default_platforms ?? [])" />
                    @endforeach
                </div>
            </div>
            <x-form-actions :cancel="route('tenant.dashboard')" />
        </form>
    </x-card>
</x-layouts.admin>
