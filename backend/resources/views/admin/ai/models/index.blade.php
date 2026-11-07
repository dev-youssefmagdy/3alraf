<x-layouts.admin guard="central" :title="__('ai.menu.models')">
    <x-card>
        <x-slot:actions>
            @can('ai_models.create')<x-button :href="route('central.ai.models.create')">{{ __('admin.add_new') }}</x-button>@endcan
        </x-slot:actions>
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <select name="provider_id" data-dt-filter="#ai-models-table" class="form-control !w-auto !py-1.5">
                <option value="">{{ __('ai.fields.provider') }}</option>
                @foreach (\App\Models\AiProvider::orderBy('name')->pluck('name', 'id') as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select name="kind" data-dt-filter="#ai-models-table" class="form-control !w-auto !py-1.5">
                <option value="">{{ __('ai.fields.kind') }}</option>
                @foreach (\App\Enums\AiModelKind::cases() as $k)<option value="{{ $k->value }}">{{ $k->label() }}</option>@endforeach
            </select>
            <input type="number" min="0" step="any" name="cost_min" data-dt-filter="#ai-models-table" placeholder="$ min" title="{{ __('ai.fields.input_cost') }}" class="form-control !w-28 !py-1.5">
            <input type="number" min="0" step="any" name="cost_max" data-dt-filter="#ai-models-table" placeholder="$ max" title="{{ __('ai.fields.input_cost') }}" class="form-control !w-28 !py-1.5">
        </div>
        <x-datatable :table="$table" :url="route('central.ai.models.datatable')" id="ai-models-table" />
    </x-card>
</x-layouts.admin>
