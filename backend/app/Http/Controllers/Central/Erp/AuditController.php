<?php

namespace App\Http\Controllers\Central\Erp;

use App\Models\Tenant;
use App\Services\Erp\Central\AnomalyService;
use App\Services\Erp\Central\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends CentralErpController
{
    private function anomalyRows(Request $r)
    {
        $this->allow('erp_central.audit');
        $r->validate(['tenant_id' => ['nullable', 'integer'], 'severity' => ['nullable', 'in:high,medium,low'], 'code' => ['nullable', 'string', 'max:40'], 'deep' => ['nullable', 'boolean']]);
        $tid = $this->intOrNull($r, 'tenant_id');
        $rows = app(AnomalyService::class)->detect($tid ? [$tid] : null, $r->boolean('deep'))
            ->when($r->filled('severity'), fn ($c) => $c->where('severity', $r->input('severity')))->when($r->filled('code'), fn ($c) => $c->where('code', $r->input('code')));
        $tenants = Tenant::whereIn('id', $rows->pluck('tenant_id')->unique())->get()->keyBy('id');

        return [$rows->values(), $tenants];
    }

    public function anomalies(Request $r)
    {
        [$rows, $tenants] = $this->anomalyRows($r);

        return view('central.erp.anomalies', ['rows' => $rows, 'tenants' => $tenants, 'allTenants' => Tenant::orderBy('name')->get(['id', 'name']), 'q' => $r->query()]);
    }

    public function exportAnomalies(Request $r)
    {
        [$rows, $tenants] = $this->anomalyRows($r);

        return Csv::download('erp-anomalies-'.now()->format('Ymd').'.csv', ['tenant', 'currency', 'severity', 'code', 'title', 'count', 'sample_entry_id', 'detail'],
            $rows->map(fn ($x) => [$tenants[$x['tenant_id']]->name ?? $x['tenant_id'], $tenants[$x['tenant_id']]->currency_code ?? '', $x['severity'], $x['code'], __('erp_central.an.'.$x['code'])[0], $x['count'], $x['entry_id'], $x['detail']])->all());
    }

    public function audit(Request $r)
    {
        $this->allow('erp_central.audit');
        $r->validate(['tenant_id' => ['nullable', 'integer'], 'action' => ['nullable', 'string', 'max:40'], 'user_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'subject' => ['nullable', 'string', 'max:40']]);
        $logs = DB::table('erp_audit_logs as a')->join('tenants as t', 't.id', '=', 'a.tenant_id')
            ->when($this->intOrNull($r, 'tenant_id'), fn ($q, $v) => $q->where('a.tenant_id', $v))->when($r->filled('action'), fn ($q) => $q->where('a.action', $r->input('action')))
            ->when($this->intOrNull($r, 'user_id'), fn ($q, $v) => $q->where('a.user_id', $v))->when($r->filled('subject'), fn ($q) => $q->where('a.subject_type', $r->input('subject')))
            ->when($this->date($r, 'from'), fn ($q, $d) => $q->where('a.created_at', '>=', $d.' 00:00:00'))->when($this->date($r, 'to'), fn ($q, $d) => $q->where('a.created_at', '<=', $d.' 23:59:59'))
            ->orderByDesc('a.id')->select('a.*', 't.name as tenant_name')->paginate(30)->withQueryString();

        return view('central.erp.audit', ['logs' => $logs, 'actions' => DB::table('erp_audit_logs')->distinct()->orderBy('action')->pluck('action'), 'tenants' => Tenant::orderBy('name')->get(['id', 'name']), 'q' => $r->query()]);
    }

    /** Backfill runs of every tenant + their failed documents (read-only). */
    public function migration(Request $r)
    {
        $this->allow('erp_central.audit');
        $r->validate(['tenant_id' => ['nullable', 'integer'], 'status' => ['nullable', 'in:running,completed,partial,failed']]);
        $runs = DB::table('erp_backfill_runs as b')->join('tenants as t', 't.id', '=', 'b.tenant_id')
            ->when($this->intOrNull($r, 'tenant_id'), fn ($q, $v) => $q->where('b.tenant_id', $v))->when($r->filled('status'), fn ($q) => $q->where('b.status', $r->input('status')))
            ->orderByDesc('b.id')->select('b.*', 't.name as tenant_name')->paginate(30)->withQueryString();
        $failed = DB::table('erp_audit_logs')->where('action', 'backfill.failed')->selectRaw('tenant_id, COUNT(*) as n')->groupBy('tenant_id')->pluck('n', 'tenant_id');

        return view('central.erp.migration', ['runs' => $runs, 'failed' => $failed, 'tenants' => Tenant::orderBy('name')->get(['id', 'name']), 'q' => $r->query()]);
    }
}
