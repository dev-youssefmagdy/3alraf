@if (! empty($canExport))
    <div class="mb-3 flex gap-2 text-sm">
        <a class="rounded border border-panel-border-strong px-3 py-1.5 text-panel-text hover:bg-panel-surface-sunken" href="{{ route('admin.erp.reports.export', ['report' => $report] + $f->query() + ['format' => 'csv']) }}">{{ __('erp_rep.export_csv') }}</a>
        <a class="rounded border border-panel-border-strong px-3 py-1.5 text-panel-text hover:bg-panel-surface-sunken" href="{{ route('admin.erp.reports.export', ['report' => $report] + $f->query() + ['format' => 'pdf']) }}">{{ __('erp_rep.export_pdf') }}</a>
    </div>
@endif
