{{-- Date range (Figma DatePicker): from / to date inputs + preset chips (last 7 / 30 / 90 days). GET form field names from / to (week starts Saturday on the native picker per locale). --}}
@props(['from' => null, 'to' => null, 'presets' => [7, 30, 90], 'branchId' => null])
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-3']) }}>
    <x-ui.input name="from" type="date" :label="__('reports.from')" :value="$from" dir="ltr" class="w-40" />
    <x-ui.input name="to" type="date" :label="__('reports.to')" :value="$to" dir="ltr" class="w-40" />
    <div class="flex flex-wrap gap-2 pb-0.5">
        @foreach ($presets as $d)
            <x-ui.chip :href="'?from='.now()->subDays($d - 1)->toDateString().'&to='.now()->toDateString().($branchId ? '&branch_id='.$branchId : '')" :selected="$from === now()->subDays($d - 1)->toDateString() && $to === now()->toDateString()">{{ __('reports.last_days', ['n' => $d]) }}</x-ui.chip>
        @endforeach
    </div>
</div>
