<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Models\Branch;
use App\Models\Erp\Account;
use App\Services\Erp\Parties\PartyService;
use App\Services\Erp\Reports\PartyLedger;
use Illuminate\Http\Request;

/** Customers & suppliers ledgers (type = customer|supplier). Views need erp.accounts.view; money movements need erp.journals.create. */
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

        return view('tenant.erp.parties', ['type' => $type, 'rows' => $rows, 'to' => $to, 'recon' => $ledger->reconcile($type),
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
        $party = $svc->party($type, $id); // 404 for another tenant's id (tenant scope)
        $from = $r->from ?: now()->startOfMonth()->toDateString();
        $to = $r->to ?: now()->toDateString();

        return view('tenant.erp.party-show', ['type' => $type, 'party' => $party, 'from' => $from, 'to' => $to, 'st' => $ledger->statement($type, $party->id, $from, $to),
            'pos' => $ledger->position($type, $party->id), 'branches' => Branch::orderBy('id')->get(), 'cashRoles' => PartyService::CASH_ROLES,
            'accounts' => Account::where('is_group', false)->where('is_active', true)->orderBy('code')->get()]);
    }

    public function ageing(Request $r, string $type, PartyLedger $ledger)
    {
        $this->allow('erp.accounts.view');
        $type = $this->type($type);
        $this->ensureSetup();
        $r->validate(['as_of' => ['nullable', 'date']]);

        return view('tenant.erp.party-ageing', ['type' => $type, 'data' => $ledger->ageing($type, $r->as_of ?: now()->toDateString())]);
    }

    /** Supplier payment / customer receipt (partial allowed; excess or advance_only becomes an advance). */
    public function settle(Request $r, string $type, int $id, PartyService $svc)
    {
        $this->allow('erp.journals.create');
        $type = $this->type($type);
        $d = $r->validate(['amount' => ['required', 'numeric', 'gt:0'], 'account' => ['required', 'in:'.implode(',', PartyService::CASH_ROLES)], 'date' => ['nullable', 'date'],
            'ref' => ['required', 'string', 'max:60'], 'advance_only' => ['nullable', 'boolean'], 'branch_id' => ['nullable', 'integer', $this->tenantExists('branches', true)], 'note' => ['nullable', 'string', 'max:300']]);
        $svc->party($type, $id);
        if (! empty($d['branch_id'])) {
            Branch::findOrFail($d['branch_id']);
        }

        return $this->guarded(function () use ($svc, $type, $id, $d) {
            $args = [$id, (string) $d['amount'], $d['ref'], $d['account'], $d['date'] ?? null, ! empty($d['advance_only']), $d['branch_id'] ?? null, $d['note'] ?? null];
            $type === 'supplier' ? $svc->pay(...$args) : $svc->receive(...$args);

            return back()->with('status', __('erp.posted'));
        });
    }

    public function applyAdvance(Request $r, string $type, int $id, PartyService $svc)
    {
        $this->allow('erp.journals.create');
        $type = $this->type($type);
        $d = $r->validate(['amount' => ['nullable', 'numeric', 'gt:0'], 'ref' => ['required', 'string', 'max:60'], 'date' => ['nullable', 'date']]);
        $svc->party($type, $id);

        return $this->guarded(function () use ($svc, $type, $id, $d) {
            $svc->applyAdvance($type, $id, $d['ref'], isset($d['amount']) ? (string) $d['amount'] : null, $d['date'] ?? null);

            return back()->with('status', __('erp.posted'));
        });
    }

    public function note(Request $r, string $type, int $id, PartyService $svc)
    {
        $this->allow('erp.journals.create');
        $type = $this->type($type);
        $d = $r->validate(['kind' => ['required', 'in:credit_note,debit_note'], 'amount' => ['required', 'numeric', 'gt:0'], 'ref' => ['required', 'string', 'max:60'],
            'account_id' => ['nullable', 'integer'], 'date' => ['nullable', 'date'], 'description' => ['nullable', 'string', 'max:300']]);
        $svc->party($type, $id);
        if (! empty($d['account_id'])) {
            abort_unless(Account::whereKey($d['account_id'])->exists(), 404);
        }

        return $this->guarded(function () use ($svc, $type, $id, $d) {
            $svc->note($type, $id, $d['kind'], (string) $d['amount'], $d['ref'], $d['account_id'] ?? null, $d['date'] ?? null, $d['description'] ?? null);

            return back()->with('status', __('erp.posted'));
        });
    }

    public function opening(Request $r, string $type, int $id, PartyService $svc)
    {
        $this->allow('erp.journals.create');
        $type = $this->type($type);
        $d = $r->validate(['amount' => ['required', 'numeric', 'gt:0'], 'ref' => ['required', 'string', 'max:60'], 'date' => ['nullable', 'date']]);
        $svc->party($type, $id);

        return $this->guarded(function () use ($svc, $type, $id, $d) {
            $svc->opening($type, $id, (string) $d['amount'], $d['ref'], $d['date'] ?? null);

            return back()->with('status', __('erp.posted'));
        });
    }
}
