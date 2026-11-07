<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Erp\Account;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Services\Erp\Reports\ErpReportFilter;
use App\Services\Erp\Reports\ReportCatalog;
use App\Services\Erp\Reports\ReportExporter;
use Illuminate\Http\Request;

class ReportController extends ErpController
{
    public const REPORTS = ['trial-balance', 'general-ledger', 'profit-loss', 'balance-sheet'];

    /** Links to report pages that live in their own section. [title key, route, permission] */
    public const EXTERNAL = [['profit_distribution', 'tenant.erp.profits.report', 'erp.profits.view'], ['asset_register', 'tenant.erp.assets.index', 'erp.assets.manage'],
        ['customer_ageing', 'tenant.erp.parties.ageing:customer', 'erp.accounts.view'], ['supplier_ageing', 'tenant.erp.parties.ageing:supplier', 'erp.accounts.view']];

    private function canBranch(): bool
    {
        return (bool) auth('tenant')->user()?->can('erp.branches.view');
    }

    private function filter(Request $r, string $report): ErpReportFilter
    {
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'branch_id' => ['nullable', 'integer'], 'account_id' => ['nullable', 'integer'], 'type' => ['nullable', 'string', 'max:30'],
            'customer_id' => ['nullable', 'integer'], 'supplier_id' => ['nullable', 'integer'], 'payment_method_id' => ['nullable', 'integer'], 'currency' => ['nullable', 'string', 'max:3']]);
        abort_unless(ReportCatalog::exists($report), 404);
        if (ReportCatalog::META[$report][2]) {
            abort_unless($this->canBranch(), 403); // branch-specific reports need erp.branches.view (others get the consolidated view only)
        }
        $f = ErpReportFilter::fromRequest($r, $this->canBranch());
        if ($f->branchId) {
            Branch::findOrFail($f->branchId); // another tenant's id -> 404
        }
        if ($f->accountId) {
            Account::findOrFail($f->accountId);
        }

        return $f;
    }

    public function index()
    {
        $this->allow('erp.reports.view');
        $this->ensureSetup();
        $u = auth('tenant')->user();
        $groups = [];
        foreach (ReportCatalog::META as $key => [$group, , $branchOnly]) {
            if ($branchOnly && ! $this->canBranch()) {
                continue;
            }
            $groups[$group][] = ['key' => $key, 'url' => route('tenant.erp.reports.show', $key)];
        }
        $external = collect(self::EXTERNAL)->filter(fn ($e) => $u->can($e[2]))->map(function ($e) {
            [$route, $p] = array_pad(explode(':', $e[1], 2), 2, null);

            return ['key' => $e[0], 'url' => route($route, $p ? ['type' => $p] : [])];
        })->all();

        return view('tenant.erp.reports-index', compact('groups', 'external'));
    }

    public function show(Request $r, string $report, ReportCatalog $cat)
    {
        $this->allow('erp.reports.view');
        $this->ensureSetup();
        $f = $this->filter($r, $report);
        $data = $cat->run($report, $f);
        $legacy = in_array($report, self::REPORTS, true);

        return view($legacy ? 'tenant.erp.report' : 'tenant.erp.report-table', [
            'report' => $report, 'rep' => $data, 'f' => $f, 'from' => $f->from, 'to' => $f->to, 'branchId' => $f->branchId, 'accountId' => $f->accountId, 'canBranch' => $this->canBranch(),
            'canExport' => (bool) auth('tenant')->user()->can('erp.reports.export'), 'filters' => ReportCatalog::META[$report][1], 'data' => $legacy ? $this->legacyData($report, $f) : null,
            'branches' => $this->canBranch() ? Branch::orderBy('id')->get() : collect(),
            'accounts' => in_array('account', ReportCatalog::META[$report][1], true) ? Account::where('is_group', false)->orderBy('code')->get() : collect(),
            'customers' => in_array('customer', ReportCatalog::META[$report][1], true) ? Customer::orderBy('name')->limit(500)->get(['id', 'name']) : collect(),
            'suppliers' => in_array('supplier', ReportCatalog::META[$report][1], true) ? Supplier::orderBy('name')->limit(500)->get(['id', 'name']) : collect(),
            'methods' => in_array('payment', ReportCatalog::META[$report][1], true) ? PaymentMethod::orderBy('id')->get() : collect(),
        ]);
    }

    /** The four statements keep their bespoke page layout. */
    private function legacyData(string $report, ErpReportFilter $f)
    {
        $rep = app(\App\Services\Erp\Reports\LedgerReports::class);

        return match ($report) {
            'trial-balance' => $rep->trialBalance($f->to, null, $f->branchId),
            'general-ledger' => ($acc = $f->accountId ? Account::find($f->accountId) : null) ? $rep->generalLedger($acc, $f->from, $f->to, $f->branchId) : null,
            'profit-loss' => $rep->profitLoss($f->from, $f->to, $f->branchId),
            'balance-sheet' => $rep->balanceSheet($f->to, $f->branchId),
        };
    }

    public function export(Request $r, string $report, ReportCatalog $cat, ReportExporter $x)
    {
        $this->allow('erp.reports.export');
        $this->ensureSetup();
        $f = $this->filter($r, $report);
        $data = $cat->run($report, $f, null); // null = export mode (50k-line safety cap on the big list reports)
        if ($r->input('format') === 'pdf') {
            return $x->pdf($data, $f)->download($x->filename($report, $f, 'pdf'));
        }

        return response($x->csv($data, $f), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$x->filename($report, $f, 'csv').'"']);
    }
}
