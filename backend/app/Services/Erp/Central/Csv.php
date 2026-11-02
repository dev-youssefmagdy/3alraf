<?php

namespace App\Services\Erp\Central;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** UTF-8 BOM CSV, formula-injection safe (same rule as the tenant ReportExporter). */
class Csv
{
    public static function cell(mixed $v): string
    {
        $v = (string) $v;
        $t = ltrim($v);

        return $t !== '' && in_array($t[0], ['=', '+', '-', '@', "\t", "\r", "\n"], true) && ! is_numeric($t) ? "'".$v : $v;
    }

    /** @param array<int,array<int,mixed>> $rows */
    public static function download(string $filename, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $r) {
                fputcsv($out, array_map([self::class, 'cell'], $r));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
