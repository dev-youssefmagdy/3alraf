<?php

namespace App\Http\Controllers\Admin\Erp;

use App\Models\Branch;
use App\Models\Erp\Account;
use App\Services\Erp\Parties\PartyService;
use App\Services\Erp\Reports\PartyLedger;
use App\Support\ErpRules;
use Illuminate\Http\Request;

/** Customers & suppliers ledgers (type = customer|supplier). Views need erp.accounts.view; money movements post at once: erp.journals.create + erp.journals.post. */
class PartyController extends ErpController
{
    private function type(string $type): string
    {
        abort_unless(in_array($type, PartyLedger::TYPES, true), 404);

        return $type;
    }

    public function index(Request $r, string $type, PartyLedger $ledger)
    {
        $this->allow('erp.accounts.view');
        $type = $this->type($type);
        $this->ensureSetup();
        $r->validate(['to' => ['nullable', 'date']]);
        $to = $r->to ?: now()->toDateString();
        $rows = $ledger->balances($type, $to);

        return view('admin.erp.parties', ['type' => $type, 'rows' => $rows, 'to' => $to, 'recon' => $ledger->reconcile($type),
            'total' => array_reduce($rows, fn ($c, $x) => bcadd($c, $x['balance'], 4), '0'),
            'directory' => $this->directory($type)]);
    }

    private function directory(string $type)
    {
        return ($type === 'customer' ? \App\Models\Customer::query() : \App\Models\Supplier::query())->orderBy('name')->limit(500)->get(['id', 'name']);
    }

    public function show(Request $r, string $type, int $id, PartyLedger $ledger, PartyService $svc)
    {
        $this->allow('erp.accounts.view');
        $type = $this->type($type);
        $this->ensureSetup();
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $party = $svc->party($type, $id); // 404 for an unknown id
        $from = $r->from ?: now()->startOfMonth()->toDateString();
        $to = $r->to ?: now()->toDateString();

        return view('admin.erp.party-show', ['type' => $type, 'party' => $party, 'from' => $from, 'to' => $to, 'st' => $ledger->statement($type, $party->id, $from, $to),
            'pos' => $ledger->position($type, $party->id), 'branches' => Branch::orderBy('id')->get(), 'cashRoles' => PartyService::CASH_ROLES,
            'accounts' => Account::where('is_group', false)->where('is_active', true)->orderBy('code')->get()]);
    }

    public function ageing(Request $r, string $type, PartyLedger $ledger)
    {
        $this->allow('erp.accounts.view');
        $type = $this->type($type);
        $this->ensureSetup();
        $r->validate(['as_of' => ['nullable', 'date']]);

        return view('admin.erp.party-ageing', ['type' => $type, 'data' => $ledger->ageing($type, $r->as_of ?: now()->toDateString())]);
    }

    /** Supplier payment / customer receipt (partial allowed; excess or advance_only becomes an advance). */
    public function settle(Request $r, string $type, int $id, PartyService $svc)
    {
        $this->allow('erp.journals.create');
        $this->allow('erp.journals.post'); // these forms post immediately (ERP-018)
        $type = $this->type($type);
        $d = $r->validate(['amount' => ErpRules::money(), 'account' => ['required', 'in:'.implode(',', PartyService::CASH_ROLES)], 'date' => ErpRules::date(false),
            'ref' => ['required', 'string', 'max:60'], 'advance_only' => ['nullable', 'boolean'], 'branch_id' => ['nullable', 'integer', $this->existsRule('branches', true)], 'note' => ['nullable', 'string', 'max:300']]);
        $svc->party($type, $id);

        return $this->guarded(function () use ($svc, $type, $id, $d) {
            $args = [$id, (string) $d['amount'], $d['ref'], $d['account'], $d['date'] ?? null, ! empty($d['advance_only']), $d['branch_id'] ?? null, $d['note'] ?? null];
            $type === 'supplier' ? $svc->pay(...$args) : $svc->receive(...$args);

            return back()->with('status', __('erp.posted'));
        });
    }

    public function applyAdvance(Request $r, string $type, int $id, PartyService $svc)
    {
        $this->allow('erp.journals.create');
        $this->allow('erp.journals.post'); // these forms post immediately (ERP-018)
        $type = $this->type($type);
        $d = $r->validate(['amount' => ErpRules::money(false), 'ref' => ['required', 'string', 'max:60'], 'date' => ErpRules::date(false)]);
        $svc->party($type, $id);

        return $this->guarded(function () use ($svc, $type, $id, $d) {
            $svc->applyAdvance($type, $id, $d['ref'], isset($d['amount']) ? (string) $d['amount'] : null, $d['date'] ?? null);

            return back()->with('status', __('erp.posted'));
        });
    }

    public function note(Request $r, string $type, int $id, PartyService $svc)
    {
        $this->allow('erp.journals.create');
        $this->allow('erp.journals.post'); // these forms post immediately (ERP-018)
        $type = $this->type($type);
        $d = $r->validate(['kind' => ['required', 'in:credit_note,debit_note'], 'amount' => ErpRules::money(), 'ref' => ['required', 'string', 'max:60'],
            'account_id' => ['nullable', 'integer', $this->existsRule('erp_accounts')->where('is_group', false)], 'date' => ErpRules::date(false), 'description' => ['nullable', 'string', 'max:300']]);
        $svc->party($type, $id);

        return $this->guarded(function () use ($svc, $type, $id, $d) {
            $svc->note($type, $id, $d['kind'], (string) $d['amount'], $d['ref'], $d['account_id'] ?? null, $d['date'] ?? null, $d['description'] ?? null);

            return back()->with('status', __('erp.posted'));
        });
    }

    public function opening(Request $r, string $type, int $id, PartyService $svc)
    {
        $this->allow('erp.journals.create');
        $this->allow('erp.journals.post'); // these forms post immediately (ERP-018)
        $type = $this->type($type);
        $d = $r->validate(['amount' => ErpRules::money(), 'ref' => ['required', 'string', 'max:60'], 'date' => ErpRules::date(false)]);
        $svc->party($type, $id);

        return $this->guarded(function () use ($svc, $type, $id, $d) {
            $svc->opening($type, $id, (string) $d['amount'], $d['ref'], $d['date'] ?? null);

            return back()->with('status', __('erp.posted'));
        });
    }
}
