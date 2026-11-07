<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Models\Erp\Account;
use App\Models\Erp\AuditLog;
use App\Models\Erp\ExchangeRate;
use App\Services\Erp\Equity\ErpSettings;
use App\Support\ErpRules;
use Illuminate\Http\Request;

class SettingsController extends ErpController
{
    public function edit(ErpSettings $s)
    {
        $this->allow('erp.settings.manage');
        $this->ensureSetup();

        return view('tenant.erp.settings', ['s' => $s->all(), 'expenseAccounts' => Account::where('type', 'expense')->where('is_group', false)->orderBy('code')->get(),
            'rates' => ExchangeRate::where('tenant_id', current_tenant()->id)->orderByDesc('effective_date')->limit(50)->get()]);
    }

    public function update(Request $r, ErpSettings $s)
    {
        $this->allow('erp.settings.manage');
        $d = $r->validate(['reserve_percent' => ErpRules::percent(), 'distribution_min_cash_buffer' => ErpRules::money(true, false),
            'distribution_requires_second_approver' => ['nullable', 'boolean'], 'distribute_prior_retained' => ['nullable', 'boolean'], 'tax_rate' => ErpRules::percent(),
            // QA-003: unknown / non-expense / group account = field error (not a 404 page)
            'tax_account_id' => ['nullable', 'integer', $this->tenantExists('erp_accounts')->where('type', 'expense')->where('is_group', false)], 'costing_policy' => ['nullable', 'string', 'max:1000'], 'revenue_policy' => ['nullable', 'string', 'max:1000'],
            'revenue_recognition' => ['nullable', 'in:'.implode(',', \App\Services\Erp\Posting\RevenueRecognition::POLICIES)], 'landed_costs' => ['nullable', 'in:expense,capitalise'],
            'journal_requires_second_poster' => ['nullable', 'boolean']]);
        foreach (['reserve_percent', 'distribution_min_cash_buffer', 'tax_rate'] as $k) {
            $s->set($k, rtrim(rtrim(ErpRules::amount($d[$k]), '0'), '.') ?: '0'); // QA-005: stored as a normalised plain decimal
        }
        foreach (['distribution_requires_second_approver', 'distribute_prior_retained', 'journal_requires_second_poster'] as $k) {
            $s->set($k, $r->boolean($k));
        }
        foreach (['revenue_recognition', 'landed_costs'] as $k) { // policy switches apply to documents from now on (posted entries are never re-timed)
            if (! empty($d[$k])) {
                $s->set($k, $d[$k]);
            }
        }
        $s->set('tax_account_id', ! empty($d['tax_account_id']) ? (int) $d['tax_account_id'] : null);
        foreach (['costing_policy', 'revenue_policy'] as $k) {
            $s->set($k, (string) ($d[$k] ?? ''));
        }
        AuditLog::create(['action' => 'settings.updated', 'subject_type' => 'erp_settings', 'subject_id' => 0, 'user_id' => auth('tenant')->id(), 'meta' => array_diff_key($d, ['costing_policy' => 1, 'revenue_policy' => 1])]);

        return back()->with('status', __('erp.saved'));
    }

    /** Tenant exchange rate (1 from = rate to), used by the central consolidated view; overrides a global rate for this tenant. */
    public function storeRate(Request $r)
    {
        $this->allow('erp.settings.manage');
        $d = $r->validate(['from_currency' => ['required', 'string', 'size:3', 'alpha'], 'to_currency' => ['required', 'string', 'size:3', 'alpha', 'different:from_currency'],
            'rate' => ErpRules::rate(), 'effective_date' => ErpRules::date()]);
        $rate = ExchangeRate::updateOrCreate(['tenant_id' => current_tenant()->id, 'from_currency' => strtoupper($d['from_currency']), 'to_currency' => strtoupper($d['to_currency']), 'effective_date' => $d['effective_date']],
            ['rate' => $d['rate'], 'created_by' => auth('tenant')->id()]);
        AuditLog::create(['action' => 'rate.saved', 'subject_type' => 'exchange_rate', 'subject_id' => $rate->id, 'user_id' => auth('tenant')->id(), 'meta' => ['pair' => $rate->from_currency.'/'.$rate->to_currency, 'rate' => $d['rate'], 'date' => $d['effective_date']]]);

        return back()->with('status', __('erp.saved'));
    }

    public function destroyRate(int $id)
    {
        $this->allow('erp.settings.manage');
        ExchangeRate::where('tenant_id', current_tenant()->id)->findOrFail($id)->delete();

        return back()->with('status', __('erp.saved'));
    }
}
