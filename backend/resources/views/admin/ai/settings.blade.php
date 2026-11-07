<x-layouts.admin :title="__('ai.menu.settings')">
    <x-card class="max-w-3xl">
        @php($quota = $setting->monthly_token_quota)
        <div class="mb-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
            {{ __('ai.usage_this_month') }}: {{ number_format($usage['tokens']) }} / {{ $quota === null ? __('ai.unlimited') : number_format($quota) }} {{ __('ai.tokens') }}
            @if ($quota)<div class="mt-2 h-2 rounded bg-slate-200"><div class="h-2 rounded bg-brand-600" style="width: {{ min(100, round($usage['tokens'] / $quota * 100)) }}%"></div></div>@endif
        </div>
        <form data-ajax-form data-method="PUT" data-validate-url="{{ route('admin.ai.settings.validate') }}" action="{{ route('admin.ai.settings.update') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <x-select name="default_ai_model_id" search :label="__('ai.fields.default_model')" :placeholder="__('ai.platform_default')" :options="$models->mapWithKeys(fn ($m) => [$m->id => $m->displayName()])->all()" :value="$setting->default_ai_model_id" />
            <x-textarea name="brand_voice" rows="4" :label="__('ai.fields.brand_voice')" :value="$setting->brand_voice" />
            <x-textarea name="forbidden_words" rows="2" :label="__('ai.fields.forbidden_words')" :value="implode(', ', $setting->forbidden_words ?? [])" />
            <div>
                <p class="form-label">{{ __('ai.fields.default_platforms') }}</p>
                <div class="flex flex-wrap gap-4">
                    @foreach (\App\Http\Requests\Admin\AiSettingsRequest::PLATFORMS as $p)
                        <x-checkbox name="default_platforms[]" :bool="false" :value="$p" :label="__('ai.platforms.'.$p)" :checked="in_array($p, $setting->default_platforms ?? [])" />
                    @endforeach
                </div>
            </div>
            <x-form-actions :cancel="route('admin.dashboard')" />
        </form>
    </x-card>
    @can('ai_platform.edit')
    @php($allowed = $setting->allowed_model_ids)
    <x-card class="mt-4 max-w-3xl" :title="__('ai.platform_card')">
        <form data-ajax-form data-method="PUT" data-validate-url="{{ route('admin.ai.settings.platform.validate') }}" action="{{ route('admin.ai.settings.platform') }}" method="POST" class="space-y-5" novalidate>
            @csrf
            <p class="text-sm text-slate-600">{{ __('ai.usage_this_month') }}: {{ number_format($usage['tokens']) }} {{ __('ai.tokens') }}, {{ $usage['images'] }} {{ __('ai.images') }}, {{ $usage['video'] }} {{ __('ai.video_secs') }}</p>
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
                    @foreach ($allModels as $m)
                        <x-checkbox name="allowed_model_ids[]" :bool="false" :value="$m->id" :label="$m->displayName().' ('.$m->kind->label().')'" :checked="$allowed !== null && in_array($m->id, $allowed)" />
                    @endforeach
                </div>
            </div>
            <x-form-actions :cancel="route('admin.dashboard')" />
        </form>
    </x-card>
    @endcan
</x-layouts.admin>
