<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Models\Erp\AuditLog;
use App\Models\Erp\BackfillRun;
use App\Services\Erp\Backfill\BackfillService;
use App\Services\Erp\Backfill\OpeningState;
use App\Services\Erp\Equity\ErpSettings;
use App\Services\Erp\Reconciliation\ReconcileRunner;
use Illuminate\Http\Request;

/** Migration card (docs/ERP_PLAN.md phase 9): backfill history, reconciliation, missing-journal detection / "post missing", auto-posting switch. Perm erp.settings.manage. */
class MigrationController extends ErpController
{
    private function page(?array $recon = null, ?array $missing = null)
    {
        $this->allow('erp.settings.manage');
        $this->ensureSetup();
        $s = app(ErpSettings::class);

        return view('tenant.erp.migration', ['runs' => BackfillRun::orderByDesc('id')->limit(30)->get(), 'auto' => $s->bool('auto_posting_enabled'), 'openingDate' => OpeningState::openingDate(),
            'recon' => $recon, 'missing' => $missing, 'benign' => array_keys(__('erp.bf.benign'))]);
    }

    public function index()
    {
        return $this->page();
    }

    public function reconcile(ReconcileRunner $runner)
    {
        $this->allow('erp.settings.manage');

        return $this->page($runner->run(current_tenant()));
    }

    public function detect(BackfillService $svc)
    {
        $this->allow('erp.settings.manage');

        return $this->page(null, $svc->missing(false));
    }

    public function postMissing(BackfillService $svc)
    {
        $this->allow('erp.settings.manage');
        $res = $svc->missing(true, auth('tenant')->id());
        $n = array_sum(array_column($res, 'created'));
        $f = array_sum(array_column($res, 'failed'));

        return redirect()->route('tenant.erp.migration.index')->with('status', __('erp.bf.posted_missing', ['n' => $n, 'f' => $f]));
    }

    public function toggle(Request $r, ErpSettings $s)
    {
        $this->allow('erp.settings.manage');
        $on = $r->boolean('enabled');
        $s->set('auto_posting_enabled', $on);
        AuditLog::create(['action' => $on ? 'auto_posting.enabled' : 'auto_posting.disabled', 'subject_type' => 'erp_settings', 'subject_id' => 0, 'user_id' => auth('tenant')->id(), 'meta' => ['by' => 'admin']]);

        return back()->with('status', __('erp.saved'));
    }
}
