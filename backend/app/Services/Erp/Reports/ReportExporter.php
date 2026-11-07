<?php

namespace App\Services\Erp\Reports;

use ArPHP\I18N\Arabic;
use Barryvdh\DomPDF\Facade\Pdf;

/** CSV (UTF-8 BOM, formula-injection safe) and PDF (dompdf, Arabic shaped like InvoicePdf) of a normalized report. */
class ReportExporter
{
    public function csv(array $report, ?ErpReportFilter $f = null): string
    {
        $out = fopen('php://temp', 'w+');
        $safe = fn (array $row) => array_map([Csv::class, 'cell'], $row);
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $safe([$report['title']]));
        if ($f) {
            fputcsv($out, [__('erp.f.from'), $f->from, __('erp.f.to'), $f->to]);
        }
        foreach ($report['cards'] as $c) {
            fputcsv($out, [Csv::cell($c['label']), $this->cell($c['value'], $c['type'])]);
        }
        foreach ($report['tables'] as $t) {
            fputcsv($out, []);
            if (! empty($t['title'])) {
                fputcsv($out, $safe([$t['title']]));
            }
            fputcsv($out, $safe(array_column($t['columns'], 'label')));
            foreach ($t['rows'] as $row) {
                fputcsv($out, array_map(fn ($c) => $this->cell($row[$c['key']] ?? '', $c['type']), $t['columns']));
            }
        }
        foreach ($report['notes'] as $n) {
            fputcsv($out, $safe([$n]));
        }
        rewind($out);

        return stream_get_contents($out);
    }

    private function cell(mixed $v, string $type): string
    {
        if (in_array($type, ['money', 'pct'], true)) {
            return $v === '' || $v === null ? '' : number_format((float) $v, 2, '.', '');
        }
        return Csv::cell($v); // every non-numeric-typed cell is formula-neutralised, whatever its declared type
    }

    public function pdf(array $report, ?ErpReportFilter $f = null): \Barryvdh\DomPDF\PDF
    {
        $rtl = locale_is_rtl();
        $shaper = $rtl ? new Arabic : null;
        $t = fn (mixed $s) => ($shaper && $s !== null && $s !== '') ? $shaper->utf8Glyphs((string) $s, 5000, false) : (string) $s;

        return Pdf::setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true])
            ->loadView('admin.erp.report-pdf', ['report' => $report, 'f' => $f, 'rtl' => $rtl, 't' => $t])->setPaper('a4', 'landscape');
    }

    public function filename(string $key, ?ErpReportFilter $f, string $ext): string
    {
        return 'erp-'.$key.'-'.($f ? $f->from.'_'.$f->to : date('Y-m-d')).'.'.$ext;
    }
}
