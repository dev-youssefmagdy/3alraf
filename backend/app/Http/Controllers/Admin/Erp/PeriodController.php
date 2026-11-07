<?php

namespace App\Http\Controllers\Admin\Erp;

use App\Models\Erp\AuditLog;
use App\Models\Erp\FiscalYear;
use App\Models\Erp\Period;
use App\Services\Erp\Accounting\PeriodService;
use Illuminate\Http\Request;

class PeriodController extends ErpController
{
    public function index(Request $r, PeriodService $svc)
    {
        $this->allow('erp.periods.view');
        $this->ensureSetup();
        $r->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = (int) ($r->year ?: now()->year);
        $svc->createYear($year);

        return view('admin.erp.periods', ['year' => $year, 'periods' => Period::whereYear('starts_on', $year)->orderBy('starts_on')->get(),
            'fy' => FiscalYear::where('starts_on', "$year-01-01")->first(),
            'log' => AuditLog::whereIn('action', ['period.closed', 'period.reopened', 'year.closed', 'year.reopened'])->latest('id')->limit(15)->get()]);
    }

    public function close(int $id, PeriodService $svc)
    {
        $this->allow('erp.periods.close');

        return $this->guarded(function () use ($id, $svc) {
            $svc->close(Period::findOrFail($id), auth('admin')->id());

            return back()->with('status', __('erp.period_closed'));
        });
    }

    public function reopen(Request $r, int $id, PeriodService $svc)
    {
        $this->allow('erp.periods.reopen');
        $d = $r->validate(['reason' => ['required', 'string', 'max:300']]);

        return $this->guarded(function () use ($id, $svc, $d) {
            $svc->reopen(Period::findOrFail($id), auth('admin')->id(), $d['reason']);

            return back()->with('status', __('erp.period_reopened'));
        });
    }

    public function closeYear(int $id, PeriodService $svc)
    {
        $this->allow('erp.periods.close');

        return $this->guarded(function () use ($id, $svc) {
            $svc->closeYear(FiscalYear::findOrFail($id), auth('admin')->id());

            return back()->with('status', __('erp.year_closed_ok'));
        });
    }

    public function reopenYear(Request $r, int $id, PeriodService $svc)
    {
        $this->allow('erp.periods.reopen');
        $d = $r->validate(['reason' => ['required', 'string', 'max:300']]);

        return $this->guarded(function () use ($id, $svc, $d) {
            $svc->reopenYear(FiscalYear::findOrFail($id), auth('admin')->id(), $d['reason']);

            return back()->with('status', __('erp.year_reopened_ok'));
        });
    }
}
