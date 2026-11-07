<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Models\Branch;
use App\Models\Erp\FixedAsset;
use App\Models\Supplier;
use App\Services\Erp\Assets\AssetService;
use App\Services\Erp\Parties\PartyService;
use App\Support\ErpRules;
use App\Support\FormToken;
use Illuminate\Http\Request;

/** Fixed assets. Pages: erp.assets.view or erp.assets.manage (ERP-028); every change needs erp.assets.manage. */
class AssetController extends ErpController
{
    public function index(AssetService $svc)
    {
        $this->allowAny('erp.assets.view', 'erp.assets.manage');
        $this->ensureSetup();

        return view('tenant.erp.assets', ['rep' => $svc->report(), 'branches' => Branch::orderBy('id')->get(), 'suppliers' => Supplier::orderBy('name')->get(['id', 'name']), 'cashRoles' => PartyService::CASH_ROLES]);
    }

    public function store(Request $r, AssetService $svc)
    {
        $this->allow('erp.assets.manage');
        $d = $r->validate(['name' => ['required', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:80'], 'acquisition_date' => ErpRules::date(), 'cost' => ErpRules::money(),
            'salvage_value' => ErpRules::money(false, false), 'useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'], 'method' => ['required', 'in:straight_line,declining_balance'],
            'branch_id' => ['nullable', 'integer', $this->tenantExists('branches', true)], 'supplier_id' => ['nullable', 'integer', $this->tenantExists('suppliers', true)], 'paid_from' => ['required', 'in:payable,'.implode(',', PartyService::CASH_ROLES)],
            'note' => ['nullable', 'string', 'max:300'], 'ref' => ['nullable', 'string', 'max:60']]);
        if (! empty($d['ref']) && ! FormToken::claim('asset', $d['ref'])) { // QA-013: a double submit registers (and posts) the asset once
            return back()->with('status', __('erp_acc.already_submitted'));
        }

        return $this->guarded(function () use ($svc, $d) {
            try {
                $svc->register($d, $d['paid_from']);
            } catch (\Throwable $e) {
                FormToken::release('asset', $d['ref'] ?? null); // nothing saved: the same form may be submitted again
                throw $e;
            }

            return back()->with('status', __('erp.saved'));
        });
    }

    public function show(int $id)
    {
        $this->allowAny('erp.assets.view', 'erp.assets.manage');
        $this->ensureSetup();
        $a = FixedAsset::with('branch')->findOrFail($id);
        $entries = \App\Models\Erp\JournalEntry::where('source_type', 'fixed_asset')->where('source_id', $a->id)->orderBy('entry_date')->orderBy('id')->get();

        return view('tenant.erp.asset-show', ['a' => $a, 'entries' => $entries, 'branches' => Branch::orderBy('id')->get(), 'cashRoles' => PartyService::CASH_ROLES]);
    }

    public function depreciate(Request $r, AssetService $svc, ?int $id = null)
    {
        $this->allow('erp.assets.manage');
        $d = $r->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $assets = $id ? [FixedAsset::findOrFail($id)] : FixedAsset::where('status', 'active')->get();

        return $this->guarded(function () use ($svc, $assets, $d) {
            $n = 0;
            foreach ($assets as $a) {
                $n += $svc->depreciate($a, $d['period']);
            }

            return back()->with('status', __('erp_ext.fa.depreciated', ['n' => $n]));
        });
    }

    public function dispose(Request $r, int $id, AssetService $svc)
    {
        $this->allow('erp.assets.manage');
        $a = FixedAsset::findOrFail($id);
        $d = $r->validate(['date' => ErpRules::date(), 'proceeds' => ErpRules::money(false, false), 'into' => ['required', 'in:'.implode(',', PartyService::CASH_ROLES)]]);

        return $this->guarded(function () use ($svc, $a, $d) {
            $svc->dispose($a, $d['date'], (string) ($d['proceeds'] ?? 0), $d['into']);

            return back()->with('status', __('erp.saved'));
        });
    }

    public function transfer(Request $r, int $id, AssetService $svc)
    {
        $this->allow('erp.assets.manage');
        $a = FixedAsset::findOrFail($id);
        $d = $r->validate(['branch_id' => ['required', 'integer', $this->tenantExists('branches', true)], 'date' => ErpRules::date(false)]);

        return $this->guarded(function () use ($svc, $a, $d) {
            $svc->transfer($a, (int) $d['branch_id'], $d['date'] ?? null);

            return back()->with('status', __('erp.saved'));
        });
    }
}
