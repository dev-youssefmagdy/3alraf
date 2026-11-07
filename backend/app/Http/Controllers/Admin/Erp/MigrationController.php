<?php

namespace App\Http\Controllers\Admin\Erp;

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

        $resolved = AuditLog::where('action', 'posting.resolved')->get(['subject_type', 'subject_id', 'id']);
        $failures = AuditLog::where('action', 'posting.failed')->where('created_at', '>=', now()->subDays(30))->orderByDesc('id')->limit(50)->get()
            ->reject(fn ($f) => $resolved->contains(fn ($r) => $r->subject_type === $f->subject_type && (int) $r->subject_id === (int) $f->subject_id && $r->id > $f->id))
            ->unique(fn ($f) => $f->subject_type.'#'.$f->subject_id)->values();

        return view('admin.erp.migration', ['failures' => $failures, 'runs' => BackfillRun::orderByDesc('id')->limit(30)->get(), 'auto' => $s->bool('auto_posting_enabled'), 'openingDate' => OpeningState::openingDate(),
            'recon' => $recon, 'missing' => $missing, 'benign' => array_keys(__('erp.bf.benign'))]);
    }

    public function index()
    {
        return $this->page();
    }

    public function reconcile(ReconcileRunner $runner)
    {
        $this->allow('erp.settings.manage');

        return $this->page($runner->run());
    }

    public function detect(BackfillService $svc)
    {
        $this->allow('erp.settings.manage');

        return $this->page(null, $svc->missing(false));
    }

    public function postMissing(BackfillService $svc)
    {
        $this->allow('erp.settings.manage');
        $res = $svc->missing(true, auth('admin')->id());
        $n = array_sum(array_column($res, 'created'));
        $f = array_sum(array_column($res, 'failed'));

        return redirect()->route('admin.erp.migration.index')->with('status', __('erp.bf.posted_missing', ['n' => $n, 'f' => $f]));
    }

    public function toggle(Request $r, ErpSettings $s)
    {
        $this->allow('erp.settings.manage');
        $on = $r->boolean('enabled');
        $s->set('auto_posting_enabled', $on);
        AuditLog::create(['action' => $on ? 'auto_posting.enabled' : 'auto_posting.disabled', 'subject_type' => 'erp_settings', 'subject_id' => 0, 'user_id' => auth('admin')->id(), 'meta' => ['by' => 'admin']]);

        return back()->with('status', __('erp.saved'));
    }

    /** ERP-016: retry a failed posting once its cause (closed period, inactive account...) is fixed; idempotent per document. */
    public function retry(int $id, \App\Services\Erp\Posting\DocumentPoster $docs)
    {
        $this->allow('erp.settings.manage');
        $log = AuditLog::where('action', 'posting.failed')->findOrFail($id);
        try {
            $ok = \Illuminate\Support\Facades\DB::transaction(fn () => $docs->post((string) $log->subject_type, (int) $log->subject_id));
        } catch (\Throwable $e) {
            return back()->withErrors(['erp' => __('erp_acc.retry_failed', ['error' => $e->getMessage()])]);
        }
        AuditLog::create(['action' => 'posting.resolved', 'subject_type' => $log->subject_type, 'subject_id' => $log->subject_id, 'user_id' => auth('admin')->id(), 'meta' => ['failure' => $log->id, 'found' => $ok]]);

        return back()->with('status', __('erp_acc.retried'));
    }
}
