<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Models\Erp\Account;
use App\Models\Erp\AuditLog;
use App\Models\Erp\ExchangeRate;
use App\Services\Erp\Equity\ErpSettings;
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
        $d = $r->validate(['reserve_percent' => ['required', 'numeric', 'min:0', 'max:100'], 'distribution_min_cash_buffer' => ['required', 'numeric', 'min:0'],
            'distribution_requires_second_approver' => ['nullable', 'boolean'], 'distribute_prior_retained' => ['nullable', 'boolean'], 'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_account_id' => ['nullable', 'integer'], 'costing_policy' => ['nullable', 'string', 'max:1000'], 'revenue_policy' => ['nullable', 'string', 'max:1000']]);
        if (! empty($d['tax_account_id'])) {
            Account::where('type', 'expense')->where('is_group', false)->findOrFail($d['tax_account_id']);
        }
        foreach (['reserve_percent', 'distribution_min_cash_buffer', 'tax_rate'] as $k) {
            $s->set($k, (string) $d[$k]);
        }
        foreach (['distribution_requires_second_approver', 'distribute_prior_retained'] as $k) {
            $s->set($k, $r->boolean($k));
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
            'rate' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'effective_date' => ['required', 'date']]);
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
