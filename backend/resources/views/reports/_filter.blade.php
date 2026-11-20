{{-- GET filter: date range (+ branch). $f ReportFilter, $branches [id=>name], $snapshot bool. Desktop: one toolbar row; presets are chips. --}}
<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl border border-panel-border bg-panel-surface p-3 shadow-panel-xs md:p-4">
    @unless ($snapshot ?? false)
        <x-ui.date-range :from="$f->from" :to="$f->to" :branchId="$f->branchId" />
    @endunless
    @if (! empty($branches))
        <x-ui.select name="branch_id" :label="__('reports.branch')" :options="$branches" :value="$f->branchId" :placeholder="__('ops.all_branches')" class="min-w-44" />
    @endif
    <x-ui.button type="submit" size="md">{{ __('reports.apply') }}</x-ui.button>
    {{ $slot ?? '' }}
</form>
