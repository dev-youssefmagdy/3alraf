<?php

namespace App\Http\Controllers\Concerns;

use App\DTOs\Reports\ReportFilter;
use App\Models\Branch;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;

/** Report index/show/CSV-export of the admin panel. */
trait HandlesReports
{
    public function __construct(private ReportService $reports) {}

    public static function middleware(): array
    {
        return [new Middleware('can:reports.view', except: ['export']), new Middleware('can:reports.export', only: ['export'])];
    }

    public function index(Request $request)
    {
        $f = ReportFilter::fromRequest($request);

        return view('reports.index', ['keys' => ReportService::KEYS, 'f' => $f]);
    }

    public function show(Request $request, string $report)
    {
        abort_unless(ReportService::exists($report), 404);
        $f = ReportFilter::fromRequest($request);

        return view('reports.show', [
            'key' => $report, 'f' => $f, 'data' => $this->reports->run($report, $f),
            'branches' => ! in_array($report, ReportService::BRANCHABLE, true) ? [] : Branch::get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(),
        ]);
    }

    public function export(Request $request, string $report)
    {
        abort_unless(ReportService::exists($report), 404);
        $f = ReportFilter::fromRequest($request);
        $data = $this->reports->run($report, $f);
        $name = 'report-'.$report.'-'.$f->from.'_'.$f->to.'.csv';

        return response($this->reports->csv($data), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$name.'"']);
    }
}
