<?php

namespace App\Http\Controllers\Concerns;

use App\DTOs\Reports\ReportFilter;
use App\Models\Branch;
use App\Models\Tenant;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;

/** Shared index/show/CSV-export for tenant and central report controllers. Using class defines $guard ('tenant'|'central'). */
trait HandlesReports
{
    public function __construct(private ReportService $reports) {}

    public static function middleware(): array
    {
        return [new Middleware('can:reports.view', except: ['export']), new Middleware('can:reports.export', only: ['export'])];
    }

    private function isCentral(): bool
    {
        return static::$guard === 'central';
    }

    public function index(Request $request)
    {
        $f = ReportFilter::fromRequest($request, $this->isCentral());

        return view('reports.index', ['guard' => static::$guard, 'keys' => ReportService::KEYS, 'f' => $f]);
    }

    public function show(Request $request, string $report)
    {
        abort_unless(ReportService::exists($report), 404);
        $f = ReportFilter::fromRequest($request, $this->isCentral());

        return view('reports.show', [
            'guard' => static::$guard, 'key' => $report, 'f' => $f, 'data' => $this->reports->run($report, $f),
            'branches' => $this->isCentral() || ! in_array($report, ReportService::BRANCHABLE, true) ? [] : Branch::get()->mapWithKeys(fn ($b) => [$b->id => $b->name])->all(),
            'tenant' => $this->isCentral() && ($id = selected_tenant_id()) ? Tenant::find($id) : null,
        ]);
    }

    public function export(Request $request, string $report)
    {
        abort_unless(ReportService::exists($report), 404);
        $f = ReportFilter::fromRequest($request, $this->isCentral());
        $data = $this->reports->run($report, $f);
        $name = ($this->isCentral() ? 'central-' : (current_tenant()->slug.'-')).$report.'-'.$f->from.'_'.$f->to.'.csv';

        return response($this->reports->csv($data), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$name.'"']);
    }
}
