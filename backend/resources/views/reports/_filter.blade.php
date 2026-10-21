{{-- GET filter: date range (+ branch in tenant context). $f ReportFilter, $branches [id=>name], $snapshot bool --}}
<form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-3">
    @unless ($snapshot ?? false)
        <div><label class="form-label">{{ __('reports.from') }}</label><input type="date" name="from" value="{{ $f->from }}" class="form-control"></div>
        <div><label class="form-label">{{ __('reports.to') }}</label><input type="date" name="to" value="{{ $f->to }}" class="form-control"></div>
        <div class="flex gap-1 pb-0.5">
            @foreach ([7, 30, 90] as $d)
                <a href="?from={{ now()->subDays($d - 1)->toDateString() }}&to={{ now()->toDateString() }}{{ $f->branchId ? '&branch_id='.$f->branchId : '' }}" class="btn-sm btn-secondary">{{ __('reports.last_days', ['n' => $d]) }}</a>
            @endforeach
        </div>
    @endunless
    @if (! empty($branches))
        <div><label class="form-label">{{ __('reports.branch') }}</label>
            <select name="branch_id" class="form-control"><option value="">{{ __('ops.all_branches') }}</option>
                @foreach ($branches as $id => $name)<option value="{{ $id }}" @selected($f->branchId === $id)>{{ $name }}</option>@endforeach
            </select></div>
    @endif
    <button class="btn btn-primary">{{ __('reports.apply') }}</button>
    {{ $slot ?? '' }}
</form>
