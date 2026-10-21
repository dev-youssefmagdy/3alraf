@php
    use App\Enums\{FeeBearer, PaymentMethodType, PaymentProvider};
    $creds = $model->credentials ?? [];
@endphp
<x-resource-form :route="$route" :editing="$editing" :model="$model" width="max-w-3xl" data-payment-form>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-select name="type" :label="__('ops.fields.type')" :options="PaymentMethodType::options()" :value="$model->type?->value ?? $model->type" />
        <div data-field>
            <label class="form-label" for="f-provider">{{ __('ops.fields.provider') }}</label>
            <select id="f-provider" name="provider" class="form-control">
                @foreach (PaymentProvider::cases() as $p)
                    <option value="{{ $p->value }}" data-type="{{ $p->type()->value }}" @selected(old('provider', $model->provider?->value ?? $model->provider) === $p->value)>{{ $p->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <x-translatable-tabs name="name" :label="__('admin.fields.name')" :languages="$languages" :values="$model->getTranslations('name')" />
    <x-translatable-tabs name="instructions" type="textarea" :label="__('ops.fields.instructions')" :languages="$languages" :values="$model->getTranslations('instructions')" />

    <div data-for-type="wallet" class="grid gap-4 sm:grid-cols-2">
        <x-input name="account_number" :label="__('ops.fields.account_number')" :value="$model->account_number" />
        <div class="flex items-end"><x-checkbox name="requires_proof" :label="__('ops.fields.requires_proof')" :checked="$model->requires_proof" /></div>
    </div>

    <div class="space-y-3 border-t border-slate-100 pt-4">
        <h3 class="text-sm font-semibold text-slate-700">{{ __('ops.fee_section') }}</h3>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-input name="fee_percent" type="number" step="0.001" min="0" max="100" :label="__('ops.fields.fee_percent')" :value="$model->fee_percent ?? 0" />
            <x-input name="fee_fixed" type="number" step="0.01" min="0" :label="__('ops.fields.fee_fixed')" :value="$model->fee_fixed ?? 0" />
            <x-select name="fee_bearer" :label="__('ops.fields.fee_bearer')" :options="FeeBearer::options()" :value="$model->fee_bearer?->value ?? $model->fee_bearer" />
        </div>
    </div>

    @foreach (PaymentProvider::cases() as $p)
        @continue(! $p->credentialFields())
        <div data-for-provider="{{ $p->value }}" class="space-y-3 border-t border-slate-100 pt-4">
            <h3 class="text-sm font-semibold text-slate-700">{{ __('ops.credentials_section') }} - {{ $p->label() }}</h3>
            <p class="text-xs text-slate-500">{{ __('ops.credentials_hint') }}</p>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($p->credentialFields() as $field => $required)
                    <x-input name="credentials[{{ $field }}]" type="password" autocomplete="new-password" :label="__('ops.credentials.'.$field)" :required="$required && ! $editing"
                        :placeholder="isset($creds[$field]) && $model->provider === $p ? '••••••••' : ''" />
                @endforeach
            </div>
            @if ($editing && $model->provider === $p)
                <p class="text-xs text-slate-500">{{ __('ops.webhook_url') }}: <code>{{ tenant_url(current_tenant(), 'webhooks/payments/'.$model->id) }}</code></p>
            @endif
        </div>
    @endforeach

    <div class="flex flex-wrap gap-6 border-t border-slate-100 pt-4">
        <span data-for-type="online"><x-checkbox name="is_sandbox" :label="__('ops.fields.is_sandbox')" :checked="$model->is_sandbox" /></span>
        <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
        <x-input name="sort_order" type="number" min="0" class="w-32" :label="__('admin.fields.sort_order')" :value="$model->sort_order ?? 0" />
    </div>
</x-resource-form>
