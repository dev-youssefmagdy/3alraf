<?php

namespace App\Http\Controllers\Admin\Erp;

use App\Models\Branch;
use App\Services\Erp\Cash\SettlementService;
use App\Support\ErpRules;
use Illuminate\Http\Request;

/** Cash & Bank card (ERP-012/014): balances of the money accounts and the settlement actions (VAT payment, pay carrier, gateway/wallet payout, POS register close). */
class CashBankController extends ErpController
{
    public const BALANCE_ROLES = ['cash', 'bank', 'pos_cash', 'gateway', 'wallet', 'carrier_receivable', 'vat_payable', 'shipping_payable', 'affiliate_payable'];

    public function index(SettlementService $svc)
    {
        $this->allow('erp.accounts.view');
        $this->ensureSetup();
        $balances = collect(self::BALANCE_ROLES)->mapWithKeys(fn ($r) => [$r => $svc->balance($r)])->all();

        return view('admin.erp.cash-bank', ['balances' => $balances, 'branches' => Branch::orderBy('id')->get(),
            'canPost' => auth('admin')->user()->can('erp.journals.post')]);
    }

    private function common(): array
    {
        return ['ref' => ['required', 'string', 'max:60'], 'date' => ErpRules::date(false), 'note' => ['nullable', 'string', 'max:300'],
            'branch_id' => ['nullable', 'integer', $this->existsRule('branches', true)]];
    }

    public function settle(Request $r, string $kind, SettlementService $svc)
    {
        $this->allow('erp.journals.post');
        abort_unless(in_array($kind, ['vat', 'carrier', 'payout'], true), 404);
        $d = $r->validate($this->common() + ['amount' => ErpRules::money(), 'fee' => ErpRules::money(false, false),
            'account' => ['required', 'in:'.implode(',', SettlementService::PAY_FROM)], 'source' => [$kind === 'payout' ? 'required' : 'nullable', 'in:'.implode(',', SettlementService::PAYOUT_SOURCES)]]);
        $b = isset($d['branch_id']) ? (int) $d['branch_id'] : null;

        return $this->guarded(function () use ($svc, $kind, $d, $b) {
            match ($kind) {
                'vat' => $svc->payVat((string) $d['amount'], $d['account'], $d['ref'], $d['date'] ?? null, $b, $d['note'] ?? null),
                'carrier' => $svc->payCarrier((string) $d['amount'], $d['account'], $d['ref'], $d['date'] ?? null, $b, $d['note'] ?? null),
                'payout' => $svc->payout($d['source'], (string) $d['amount'], (string) ($d['fee'] ?? '0'), $d['account'], $d['ref'], $d['date'] ?? null, $b, $d['note'] ?? null),
            };

            return back()->with('status', __('erp.posted'));
        });
    }
}
