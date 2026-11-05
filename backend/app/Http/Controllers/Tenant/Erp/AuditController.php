<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Models\Erp\AuditLog;
use App\Models\TenantAdmin;
use Illuminate\Http\Request;

/** ERP-029: the tenant's own ERP audit trail (postings, reversals, period closes, settings changes...). Needs erp.audit.view. Read-only. */
class AuditController extends ErpController
{
    public function index(Request $r)
    {
        $this->allow('erp.audit.view');
        $str = fn (string $k) => is_string($v = $r->query($k)) && $v !== '' ? mb_substr($v, 0, 60) : null; // arrays / garbage are ignored (QA-008)
        $date = fn (string $k) => ($v = $str($k)) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
        $user = ($u = $str('user_id')) && ctype_digit($u) && strlen($u) <= 18 ? (int) $u : null;
        $logs = AuditLog::query()->when($str('action'), fn ($q, $v) => $q->where('action', $v))->when($user, fn ($q, $v) => $q->where('user_id', $v))
            ->when($date('from'), fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))->when($date('to'), fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->orderByDesc('id')->paginate(30)->withQueryString();

        return view('tenant.erp.audit', ['logs' => $logs, 'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'users' => TenantAdmin::whereIn('id', $logs->pluck('user_id')->filter()->unique())->pluck('name', 'id'),
            'q' => ['action' => $str('action'), 'user_id' => $user, 'from' => $date('from'), 'to' => $date('to')]]);
    }
}
