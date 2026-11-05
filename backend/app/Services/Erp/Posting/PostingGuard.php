<?php

namespace App\Services\Erp\Posting;

use App\Models\Erp\AuditLog;
use App\Models\Tenant;
use Closure;
use Throwable;

/**
 * Runs a posting inside the right tenant context and never lets a ledger problem (closed period, missing account, ...) break the
 * originating business operation. A failure is NEVER silent: it is reported and written to erp_audit_logs as `posting.failed`
 * (subject, key facts, message) so it shows up as an anomaly and can be re-posted after the cause is fixed (idempotent keys).
 */
class PostingGuard
{
    public function run(int $tenantId, string $subjectType, int $subjectId, string $what, Closure $fn): mixed
    {
        $previous = app()->bound('currentTenant') ? app('currentTenant') : null;
        try {
            if (! $previous || $previous->id !== $tenantId) {
                $tenant = Tenant::find($tenantId);
                if (! $tenant) {
                    return null;
                }
                app()->instance('currentTenant', $tenant);
            }

            if (! app(\App\Services\Erp\Equity\ErpSettings::class)->bool('auto_posting_enabled')) {
                return null; // tenant not enabled for auto-posting yet (no opening balances): record nothing, erp:backfill picks the document up later
            }

            return $fn();
        } catch (Throwable $e) {
            report($e);
            try {
                $known = AuditLog::where('action', 'posting.failed')->where('subject_type', $subjectType)->where('subject_id', $subjectId)
                    ->where('created_at', '>=', now()->subDay())->exists();
                AuditLog::create(['action' => 'posting.failed', 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'user_id' => auth('tenant')->id(),
                    'meta' => ['event' => $what, 'error' => $e->getMessage(), 'exception' => $e::class]]);
                if (! $known) { // ERP-016: never silent - ERP managers are told (once per document per day); retry from the Migration page
                    $note = new \App\Notifications\ErpNotification('erp_posting_failed', ['subject_type' => $subjectType, 'subject_id' => $subjectId, 'event' => $what, 'error' => $e->getMessage()]);
                    \App\Models\TenantAdmin::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true)->get()
                        ->filter(fn ($a) => \Illuminate\Support\Facades\Gate::forUser($a)->allows('erp.settings.manage'))->each(fn ($a) => $a->notify($note));
                }
            } catch (Throwable $inner) {
                report($inner);
            }

            return null;
        } finally {
            if ($previous) {
                app()->instance('currentTenant', $previous);
            } elseif (app()->bound('currentTenant')) {
                app()->forgetInstance('currentTenant');
                app()->offsetUnset('currentTenant'); // never leak the tenant we switched to (queue workers, console)
            }
        }
    }
}
