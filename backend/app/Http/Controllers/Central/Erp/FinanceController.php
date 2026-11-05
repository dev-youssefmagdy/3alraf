<?php

namespace App\Http\Controllers\Central\Erp;

use App\Models\Branch;
use App\Models\Tenant;
use App\Services\Erp\Central\CentralErpService;
use App\Services\Erp\Central\Csv;
use App\Services\Erp\Central\ExchangeRates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends CentralErpController
{
    public function __construct(private CentralErpService $svc) {}

    private function overviewRows(Request $r)
    {
        $this->allow('erp_central.reports');
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'sort' => ['nullable', 'string', 'max:20'], 'dir' => ['nullable', 'in:asc,desc'],
            'currency' => ['nullable', 'string', 'max:3'], 'q' => ['nullable', 'string', 'max:100'], 'period' => ['nullable', 'in:open,closed'], 'anomalies' => ['nullable', 'in:yes']]);

        return $this->svc->overview($this->date($r, 'from'), $this->date($r, 'to'), $r->only(['q', 'currency', 'period', 'anomalies', 'sort', 'dir']));
    }

    public function overview(Request $r)
    {
        $rows = $this->overviewRows($r);

        return view('central.erp.overview', ['rows' => $rows, 'currencies' => Tenant::query()->distinct()->orderBy('currency_code')->pluck('currency_code'), 'q' => $r->query()]);
    }

    public function exportOverview(Request $r)
    {
        $rows = $this->overviewRows($r);

        return Csv::download('erp-tenant-overview-'.now()->format('Ymd').'.csv',
            ['tenant', 'currency', 'revenue', 'expenses', 'net_profit', 'cash', 'receivables', 'payables', 'inventory', 'equity', 'last_posting', 'open_periods', 'closed_periods', 'anomalies'],
            $rows->map(fn ($x) => [$x['name'], $x['currency'], $x['revenue'], $x['expenses'], $x['net'], $x['cash'], $x['ar'], $x['ap'], $x['inventory'], $x['equity'], $x['last_posting'], $x['periods_open'], $x['periods_closed'], $x['anomalies']])->all());
    }

    public function tenant(Request $r, int $id)
    {
        $this->allow('erp_central.reports');
        $tenant = Tenant::findOrFail($id);
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $to = $this->date($r, 'to') ?? now()->toDateString();
        $from = $this->date($r, 'from') ?? now()->startOfYear()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return view('central.erp.tenant', ['tenant' => $tenant, 'from' => $from, 'to' => $to, 'reports' => $this->svc->statements($tenant, $from, $to),
            'row' => $this->svc->overview(null, $to)->firstWhere('id', $tenant->id)]);
    }

    /** Tenants side by side inside each currency group. */
    public function performance(Request $r)
    {
        $this->allow('erp_central.reports');
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'convert_to' => ['nullable', 'string', 'size:3']]);
        $rows = $this->svc->overview($this->date($r, 'from'), $this->date($r, 'to'));

        return view('central.erp.performance', ['groups' => $this->svc->groups($rows, $r->input('convert_to')), 'q' => $r->query(), 'currencies' => Tenant::query()->distinct()->pluck('currency_code')]);
    }

    public function consolidated(Request $r)
    {
        $this->allow('erp_central.reports');
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'convert_to' => ['nullable', 'string', 'size:3']]);
        $rows = $this->svc->overview($this->date($r, 'from'), $this->date($r, 'to'));

        return view('central.erp.consolidated', ['groups' => $this->svc->groups($rows, $r->input('convert_to')), 'q' => $r->query(), 'currencies' => Tenant::query()->distinct()->pluck('currency_code')]);
    }

    public function transactions(Request $r)
    {
        $this->allow('erp_central.reports');
        $r->validate(['tenant_id' => ['nullable', 'integer'], 'branch_id' => ['nullable', 'integer'], 'account' => ['nullable', 'string', 'max:32'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,posted,reversed'], 'type' => ['nullable', 'string', 'max:12'], 'source' => ['nullable', 'string', 'max:40'], 'q' => ['nullable', 'string', 'max:100']]);
        $q = DB::table('erp_journal_entries as e')->join('tenants as t', 't.id', '=', 'e.tenant_id')
            ->when($this->intOrNull($r, 'tenant_id'), fn ($q, $v) => $q->where('e.tenant_id', $v))
            ->when($this->intOrNull($r, 'branch_id'), fn ($q, $v) => $q->where('e.branch_id', $v))
            ->when($r->filled('status'), fn ($q) => $q->where('e.status', $r->input('status')))->when($r->filled('type'), fn ($q) => $q->where('e.type', $r->input('type')))
            ->when($r->filled('source'), fn ($q) => $q->where('e.source_type', $r->input('source')))
            ->when($this->date($r, 'from'), fn ($q, $d) => $q->where('e.entry_date', '>=', $d))->when($this->date($r, 'to'), fn ($q, $d) => $q->where('e.entry_date', '<=', $d))
            ->when($r->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('e.number', 'like', '%'.$r->input('q').'%')->orWhere('e.description', 'like', '%'.$r->input('q').'%')))
            ->when($r->filled('account'), fn ($q) => $q->whereExists(fn ($x) => $x->select(DB::raw(1))->from('erp_journal_lines as l')->join('erp_accounts as a', 'a.id', '=', 'l.account_id')
                ->whereColumn('l.entry_id', 'e.id')->where('a.code', $r->input('account'))));
        $entries = $q->orderByDesc('e.entry_date')->orderByDesc('e.id')->select('e.*', 't.name as tenant_name', 't.currency_code as tenant_currency')->paginate(25)->withQueryString();

        return view('central.erp.transactions', [
            'entries' => $entries, 'tenants' => Tenant::orderBy('name')->get(['id', 'name']), 'q' => $r->query(),
            'branches' => Branch::withoutGlobalScopes()->withTrashed()->when($this->intOrNull($r, 'tenant_id'), fn ($q, $t) => $q->where('tenant_id', $t))->orderBy('id')->limit(300)->get(['id', 'name', 'tenant_id']),
            'sources' => DB::table('erp_journal_entries')->whereNotNull('source_type')->distinct()->orderBy('source_type')->pluck('source_type'),
        ]);
    }

    public function entry(int $id)
    {
        $this->allow('erp_central.reports');
        $e = DB::table('erp_journal_entries as e')->join('tenants as t', 't.id', '=', 'e.tenant_id')->where('e.id', $id)->select('e.*', 't.name as tenant_name', 't.currency_code as tenant_currency')->first();
        abort_unless($e, 404);
        // lines are read by entry id AND the entry's own tenant: tenants never mix
        $lines = DB::table('erp_journal_lines as l')->join('erp_accounts as a', 'a.id', '=', 'l.account_id')->where('l.entry_id', $e->id)->where('l.tenant_id', $e->tenant_id)->orderBy('l.id')
            ->select('l.*', 'a.code as account_code', 'a.name as account_name')->get()->map(function ($l) {
                $n = json_decode((string) $l->account_name, true);
                $l->account_label = is_array($n) ? ($n[app()->getLocale()] ?? $n['en'] ?? reset($n) ?: '') : (string) $l->account_name;

                return $l;
            });
        $branch = $e->branch_id ? Branch::withoutGlobalScopes()->withTrashed()->find($e->branch_id)?->name : null;

        return view('central.erp.entry', ['e' => $e, 'lines' => $lines, 'branch' => $branch]);
    }

    public function parties(Request $r)
    {
        $this->allow('erp_central.reports');
        $tid = $this->intOrNull($r, 'tenant_id');
        $parties = $this->svc->parties($tid);

        return view('central.erp.parties', ['parties' => $parties, 'ageing' => $r->boolean('ageing') ? $this->svc->ageing($parties->keys()->all()) : null, 'tenants' => Tenant::orderBy('name')->get(['id', 'name']), 'q' => $r->query()]);
    }

    public function inventory()
    {
        $this->allow('erp_central.reports');

        return view('central.erp.inventory', ['rows' => $this->svc->inventory()]);
    }

    public function periods(Request $r)
    {
        $this->allow('erp_central.reports');

        return view('central.erp.periods', $this->svc->periods($this->intOrNull($r, 'tenant_id')) + ['q' => $r->query(), 'overview' => $this->svc->overview()->keyBy('id'), 'allTenants' => Tenant::orderBy('name')->get(['id', 'name'])]);
    }
}
