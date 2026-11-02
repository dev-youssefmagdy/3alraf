@if (! empty($canExport))
    <div class="mb-3 flex gap-2 text-sm">
        <a class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50" href="{{ route('tenant.erp.reports.export', ['report' => $report] + $f->query() + ['format' => 'csv']) }}">{{ __('erp_rep.export_csv') }}</a>
        <a class="rounded border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50" href="{{ route('tenant.erp.reports.export', ['report' => $report] + $f->query() + ['format' => 'pdf']) }}">{{ __('erp_rep.export_pdf') }}</a>
    </div>
@endif
