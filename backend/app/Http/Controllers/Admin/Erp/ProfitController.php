<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Exceptions\AccountingException;
use App\Models\Erp\Owner;
use App\Models\Erp\ProfitDistribution;
use App\Models\Erp\ProfitDistributionLine;
use App\Services\Erp\Equity\ErpSettings;
use App\Services\Erp\Equity\ProfitDistributionService;
use App\Services\Erp\Equity\ProfitService;
use App\Services\Erp\Parties\PartyService;
use App\Support\ErpRules;
use Illuminate\Http\Request;

/** Profit calculation + controlled distribution. View = erp.profits.view; every state change = erp.profits.distribute. */
class ProfitController extends ErpController
{
    public function index(Request $r, ProfitService $profit, ProfitDistributionService $svc)
    {
        $this->allow('erp.profits.view');
        $this->ensureSetup();
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $r->from ?: now()->startOfMonth()->toDateString();
        $to = $r->to ?: now()->toDateString();
        $calc = $profit->compute($from, $to);
        $ready = null;
        try {
            $svc->assertReady($from, $to, true);
        } catch (AccountingException $e) {
            $ready = $e->getMessage();
        }

        return view('tenant.erp.profits', ['from' => $from, 'to' => $to, 'calc' => $calc, 'ready' => $ready, 'runs' => ProfitDistribution::withCount('lines')->latest('id')->limit(30)->get(),
            'owners' => Owner::where('is_eligible', true)->where('ownership_percent', '>', 0)->orderBy('id')->get(), 'canDistribute' => auth('tenant')->user()->can('erp.profits.distribute')]);
    }

    public function store(Request $r, ProfitDistributionService $svc)
    {
        $this->allow('erp.profits.distribute');
        $d = $r->validate(['from' => ErpRules::date(), 'to' => ErpRules::date(), 'retain_percent' => ErpRules::percent(false), 'retain_amount' => ErpRules::money(false, false),
            'acknowledge_open' => ['nullable', 'boolean'], 'overrides' => ['nullable', 'array'], 'overrides.*' => ErpRules::money(false, false), 'note' => ['nullable', 'string', 'max:300']]);
        $d['overrides'] = array_filter($d['overrides'] ?? [], fn ($v) => $v !== null && $v !== '');

        return $this->guarded(function () use ($svc, $d) {
            $dist = $svc->create($d, auth('tenant')->id());

            return redirect()->route('tenant.erp.profits.show', $dist->id)->with('status', __('erp_dist.created'));
        });
    }

    public function show(int $id)
    {
        $this->allow('erp.profits.view');
        $dist = ProfitDistribution::with(['lines.owner', 'creator', 'approver', 'entry'])->findOrFail($id);

        return view('tenant.erp.profit-show', ['d' => $dist, 'cashRoles' => PartyService::CASH_ROLES, 'second' => app(ErpSettings::class)->bool('distribution_requires_second_approver'),
            'canDistribute' => auth('tenant')->user()->can('erp.profits.distribute')]);
    }

    private function act(int $id, callable $fn, string $msg)
    {
        $this->allow('erp.profits.distribute');
        $dist = ProfitDistribution::findOrFail($id);

        return $this->guarded(function () use ($fn, $dist, $msg) {
            $fn($dist);

            return back()->with('status', __($msg));
        });
    }

    public function approve(int $id, ProfitDistributionService $svc)
    {
        return $this->act($id, fn ($d) => $svc->approve($d, auth('tenant')->id()), 'erp_dist.approved');
    }

    public function post(int $id, ProfitDistributionService $svc)
    {
        return $this->act($id, fn ($d) => $svc->post($d, auth('tenant')->id()), 'erp_dist.posted');
    }

    public function cancel(Request $r, int $id, ProfitDistributionService $svc)
    {
        $reason = (string) ($r->validate(['reason' => ['nullable', 'string', 'max:300']])['reason'] ?? '');

        return $this->act($id, fn ($d) => $svc->cancel($d, auth('tenant')->id(), $reason), 'erp_dist.cancelled');
    }

    public function pay(Request $r, int $id, int $line, ProfitDistributionService $svc)
    {
        $this->allow('erp.profits.distribute');
        $d = $r->validate(['account' => ['required', 'in:'.implode(',', PartyService::CASH_ROLES)], 'date' => ErpRules::date(false)]);
        $l = ProfitDistributionLine::where('distribution_id', ProfitDistribution::findOrFail($id)->id)->findOrFail($line);

        return $this->guarded(function () use ($svc, $l, $d) {
            $svc->pay($l, $d['account'], auth('tenant')->id(), $d['date'] ?? null);

            return back()->with('status', __('erp_dist.paid'));
        });
    }

    public function report(Request $r, ProfitDistributionService $svc)
    {
        $this->allow('erp.profits.view');
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $r->from ?: now()->startOfYear()->toDateString();
        $to = $r->to ?: now()->toDateString();

        return view('tenant.erp.profit-report', ['from' => $from, 'to' => $to, 'rows' => $svc->report($from, $to)]);
    }

    /** Printable owner distribution statement (all non-cancelled runs of one owner, or one run). */
    public function statement(Request $r, int $owner, ProfitDistributionService $svc)
    {
        $this->allow('erp.profits.view');
        $o = Owner::findOrFail($owner);

        return view('tenant.erp.profit-statement', ['owner' => $o, 'lines' => $svc->ownerLines($o)->when($r->distribution, fn ($c) => $c->where('distribution_id', (int) $r->distribution)), 'tenant' => current_tenant()]);
    }
}
