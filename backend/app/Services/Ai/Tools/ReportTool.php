<?php

namespace App\Services\Ai\Tools;

use App\Services\Reports\ReportService;

/** Thin wrapper helpers over ReportService so reports math is never duplicated. */
abstract class ReportTool extends AbstractTool
{
    protected function reports(): ReportService
    {
        return app(ReportService::class);
    }

    protected function cards(array $report): array
    {
        $o = [];
        foreach ($report['cards'] as $c) {
            $o[$c['label']] = $c['value'];
        }

        return $o;
    }

    protected function rows(array $report, array $fields, int $n): array
    {
        return array_map(fn ($r) => array_intersect_key($r, array_flip($fields)), array_slice($report['rows'], 0, $n));
    }
}
