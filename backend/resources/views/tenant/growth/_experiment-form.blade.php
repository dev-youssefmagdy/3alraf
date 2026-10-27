<form data-ajax-form @if ($method !== 'POST') data-method="{{ $method }}" @endif action="{{ $action }}" method="POST" class="grid gap-3 sm:grid-cols-3" novalidate>
    @csrf
    <x-input name="name" :label="__('growth_hub.fields.name')" :value="$exp?->name" required class="sm:col-span-2" />
    <x-input name="metric" :label="__('growth_hub.fields.metric')" :value="$exp?->metric" />
    <x-textarea name="hypothesis" :label="__('growth_hub.fields.hypothesis')" :value="$exp?->hypothesis" class="sm:col-span-3" />
    <x-input name="objective" :label="__('growth_hub.fields.objective')" :value="$exp?->objective" class="sm:col-span-3" />
    <x-input name="starts_on" type="date" :label="__('growth_hub.fields.starts_on')" :value="$exp?->starts_on?->format('Y-m-d')" />
    <x-input name="ends_on" type="date" :label="__('growth_hub.fields.ends_on')" :value="$exp?->ends_on?->format('Y-m-d')" />
    <x-input name="budget_cap" type="number" step="0.01" min="0" :label="__('growth_hub.fields.budget_cap')" :value="$exp?->budget_cap" :hint="__('growth_hub.experiments.cap_hint', ['cap' => app(\App\Services\Growth\GrowthConfig::class)->get('ad_total_budget_cap')])" />
    <x-select name="status" :label="__('admin.status')" :options="__('growth_hub.experiments.statuses')" :value="$exp?->status ?? 'draft'" />
    <x-select name="outcome" :label="__('growth_hub.fields.outcome')" :options="__('growth_hub.experiments.outcomes')" :value="$exp?->outcome" :placeholder="'—'" />
    <div class="sm:col-span-3"><x-textarea name="learnings" :label="__('growth_hub.fields.learnings')" :value="$exp?->learnings" /></div>
    <div class="sm:col-span-3"><x-button type="submit">{{ __('admin.save') }}</x-button></div>
</form>
