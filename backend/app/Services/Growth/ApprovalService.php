<?php

namespace App\Services\Growth;

use App\Models\GrowthApproval;
use App\Repositories\Contracts\GrowthApprovalRepositoryInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Human approval queue. Later sprints register a handler per type (`register('bundle_draft', fn (GrowthApproval $a): array => ...)`);
 * approve runs it inside a transaction under a row lock, so a second approve is refused (422 `state`) and a failing handler leaves it pending.
 */
class ApprovalService
{
    /** @var array<string,Closure> */
    private array $handlers = [];

    public function __construct(private ActionLogger $log, private GrowthApprovalRepositoryInterface $repo)
    {
        // Built-in no-op type: a note / task for a human (useful for n8n proposals before module handlers exist).
        $this->register('note', fn (GrowthApproval $a): array => ['acknowledged' => true]);
    }

    public function register(string $type, Closure $handler): void
    {
        $this->handlers[$type] = $handler;
    }

    public function types(): array
    {
        return array_keys($this->handlers);
    }

    public function request(string $type, string $title, array $payload, ?Model $subject = null, string $requestedBy = 'system', ?int $ttlDays = null): GrowthApproval
    {
        if (! isset($this->handlers[$type])) {
            throw ValidationException::withMessages(['type' => __('growth_hub.approvals.unknown_type', ['type' => $type])]);
        }
        $a = $this->repo->create([
            'type' => $type, 'title' => mb_substr($title, 0, 200), 'payload' => PiiScrubber::scrub($payload), 'status' => GrowthApproval::PENDING, 'requested_by' => $requestedBy,
            'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(), 'expires_at' => now()->addDays($ttlDays ?? (int) config('growth.approvals.ttl_days')),
        ]);
        $this->log->log('approval.requested', str_starts_with($requestedBy, 'client:') ? 'n8n' : (str_starts_with($requestedBy, 'admin:') ? 'admin' : 'system'), null, null, $a, 'ok', ['type' => $type]);

        return $a;
    }

    public function approve(int $id, int $adminId, ?string $reason = null): GrowthApproval
    {
        try {
            $a = DB::transaction(function () use ($id, $adminId, $reason) {
                $a = $this->repo->lockForUpdate($id);
                $this->assertDecidable($a);
                $handler = $this->handlers[$a->type] ?? null;
                if (! $handler) {
                    throw ValidationException::withMessages(['state' => __('growth_hub.approvals.no_handler')]);
                }
                $result = $handler($a);
                $a->update(['status' => GrowthApproval::APPROVED, 'decided_by' => $adminId, 'decided_at' => now(), 'reason' => $reason, 'result' => $result ?: null]);

                return $a;
            });
        } catch (ValidationException|ModelNotFoundException $e) {
            throw $e;
        } catch (ApprovalExpiredException) {
            $this->markExpired($id);
            throw ValidationException::withMessages(['state' => __('growth_hub.approvals.expired')]);
        } catch (\Throwable $e) {
            $this->log->log('approval.approve', 'admin', $adminId, $reason, $this->repo->find($id), 'failed', ['error' => class_basename($e)]);
            throw ValidationException::withMessages(['state' => __('growth_hub.approvals.handler_failed')]);
        }
        $this->log->log('approval.approved', 'admin', $adminId, $reason, $a, 'ok', ['type' => $a->type]);

        return $a;
    }

    public function reject(int $id, int $adminId, string $reason): GrowthApproval
    {
        try {
            $a = DB::transaction(function () use ($id, $adminId, $reason) {
                $a = $this->repo->lockForUpdate($id);
                $this->assertDecidable($a);
                $a->update(['status' => GrowthApproval::REJECTED, 'decided_by' => $adminId, 'decided_at' => now(), 'reason' => $reason]);

                return $a;
            });
        } catch (ApprovalExpiredException) {
            $this->markExpired($id);
            throw ValidationException::withMessages(['state' => __('growth_hub.approvals.expired')]);
        }
        $this->log->log('approval.rejected', 'admin', $adminId, $reason, $a, 'rejected', ['type' => $a->type]);

        return $a;
    }

    public function expireDue(): int
    {
        return $this->repo->expireDue(app(GrowthFeatures::class)->tenantsWith('approvals')->pluck('id'));
    }

    private function assertDecidable(GrowthApproval $a): void
    {
        if (! $a->isPending()) {
            throw ValidationException::withMessages(['state' => __('growth_hub.approvals.already', ['status' => $a->status])]);
        }
        if ($a->expires_at && $a->expires_at->isPast()) {
            throw new ApprovalExpiredException; // marked expired AFTER the transaction rolls back (an update here would be undone)
        }
    }

    private function markExpired(int $id): void
    {
        $this->repo->markExpired($id);
    }
}

/** Internal signal: the approval passed its expiry while being decided. */
class ApprovalExpiredException extends \RuntimeException {}
