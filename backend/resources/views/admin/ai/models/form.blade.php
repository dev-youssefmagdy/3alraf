@php($editing = $model->exists)
<x-layouts.admin guard="central" :title="$editing ? __('admin.edit').': '.$model->label : __('admin.add_new')">
    <x-card class="max-w-2xl">
        <form data-ajax-form @if ($editing) data-method="PUT" @endif
            data-validate-url="{{ route('central.ai.models.validate', $editing ? [$model] : []) }}"
            action="{{ $editing ? route('central.ai.models.update', $model) : route('central.ai.models.store') }}" method="POST" class="space-y-4" novalidate>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="ai_provider_id" search required :label="__('ai.fields.provider')" :options="$providers" :value="$model->ai_provider_id" />
                <x-select name="kind" required :label="__('ai.fields.kind')" :options="\App\Enums\AiModelKind::options()" :value="$model->kind?->value" />
                <x-input name="model_id" required :label="__('ai.fields.model_id')" :value="$model->model_id" />
                <x-input name="label" required :label="__('ai.fields.label')" :value="$model->label" />
                <x-input name="input_cost" type="number" step="0.0001" min="0" :label="__('ai.fields.input_cost')" :value="$model->input_cost" />
                <x-input name="output_cost" type="number" step="0.0001" min="0" :label="__('ai.fields.output_cost')" :value="$model->output_cost" />
                <x-input name="max_output" type="number" min="1" :label="__('ai.fields.max_output')" :value="$model->max_output" />
            </div>
            <div>
                <p class="form-label">{{ __('ai.fields.capabilities') }}</p>
                <div class="flex flex-wrap gap-4">
                    @foreach (\App\Http\Requests\Central\Ai\AiModelRequest::CAPABILITIES as $c)
                        <x-checkbox name="capabilities[]" :bool="false" :value="$c" :label="__('ai.capabilities.'.$c)" :checked="$model->can($c)" />
                    @endforeach
                </div>
            </div>
            <div class="flex flex-wrap gap-6">
                <x-checkbox name="is_active" :label="__('admin.fields.is_active')" :checked="$model->is_active" />
                <x-checkbox name="is_default" :label="__('ai.fields.is_default')" :checked="$model->is_default" />
            </div>
            <x-form-actions :cancel="route('central.ai.models.index')" />
        </form>
    </x-card>
</x-layouts.admin>
