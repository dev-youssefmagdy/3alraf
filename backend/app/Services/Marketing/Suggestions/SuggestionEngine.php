<?php

namespace App\Services\Marketing\Suggestions;

use App\DTOs\Marketing\SuggestionEditData;
use App\DTOs\Marketing\SuggestionInput;
use App\Enums\Marketing\SuggestionExecutionStatus as X;
use App\Enums\Marketing\SuggestionStatus as S;
use App\Models\Admin;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionActivityLog;
use App\Models\MarketingSuggestionApproval;
use App\Models\MarketingSuggestionExecution;
use App\Models\MarketingSuggestionRevision;
use App\Repositories\Contracts\MarketingSuggestionRepositoryInterface;
use App\Services\Marketing\Suggestions\Types\BlogSuggestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The one reusable approval + execution engine of the Suggestions Center (MCP.md §16.3, §23; docs/marketing/APPROVAL_AND_EXECUTION_ENGINE.md).
 * - create: handler validates/normalises, content hash, duplicate + recently-rejected guard, target snapshot (stale detection), revision 1.
 * - edit: new version + revision with diff; an edit after approval invalidates the approval and returns the suggestion to `edited`.
 * - approve / reject: explicit, by a permitted admin, bound to (version, content_hash), approval expires after N hours.
 * - apply: valid approval for the CURRENT version/hash, target unchanged since the snapshot (else 409 stale_target until re-snapshotted),
 *   idempotent (unique idempotency key; an applied suggestion is never applied twice), handler runs in a transaction, failure ⇒ `failed`.
 * - rollback where the handler supports it (applied → archived + rolled_back_at).
 * Every action is written to marketing_suggestion_activity_logs. Status moves are checked by SuggestionStateMachine.
 */
class SuggestionEngine
{
    public function __construct(private SuggestionTypeRegistry $types, private MarketingSuggestionRepositoryInterface $repo) {}

    public function registry(): SuggestionTypeRegistry
    {
        return $this->types;
    }

    // ------------------------------------------------------------------ create / edit

    /** @return array{0: MarketingSuggestion, 1: list<string>} suggestion + warnings */
    public function create(SuggestionInput $in, SuggestionActor $actor): array
    {
        $h = $this->types->get($in->type);
        if ($in->targetType !== null && ! in_array($in->targetType, $h->targetTypes(), true)) {
            throw SuggestionException::validation(['target_entity_type' => __('marketing.suggestions.errors.bad_target_type')]);
        }
        $payload = $h->normalize($in->payload, $in->targetType);
        $target = $h->resolveTarget($in->targetType, $in->targetId);
        if (($in->targetType !== null || $h->targetRequired($payload)) && ! $target) {
            throw SuggestionException::make('target_not_found', 422);
        }
        $check = $h->check($payload, $target);
        if ($check['errors']) {
            throw SuggestionException::validation($check['errors']);
        }
        $targetId = $target && $in->targetType !== 'site' ? (int) $target->getKey() : null;
        $hash = SuggestionHasher::content($in->type, $in->targetType, $targetId, $payload);
        if ($dup = $this->repo->openDuplicate($in->type, $hash)) {
            throw SuggestionException::make('duplicate_suggestion', 409, ['existing_id' => $dup->id]);
        }
        $days = (int) config('marketing.suggestions.rejected_cooldown_days', 30);
        if (($rej = $this->repo->recentlyRejected($in->type, $hash, $days)) && mb_strlen(trim((string) $in->forceReason)) < 10) {
            throw SuggestionException::make('recently_rejected', 409, ['rejected_id' => $rej->id, 'cooldown_days' => $days], ['days' => $days]);
        }
        $snapshot = $h->snapshot($target, $payload);

        return DB::transaction(function () use ($in, $actor, $payload, $hash, $snapshot, $targetId, $check, $rej) {
            $meta = $in->sourceMeta + ($actor->isToken() ? ['token_id' => $actor->id] : []);
            if ($rej && $in->forceReason) {
                $meta['force_reason'] = mb_substr(trim($in->forceReason), 0, 500);
                $meta['overrides_rejected_id'] = $rej->id;
            }
            $s = $this->repo->create([
                'type' => $in->type, 'title' => mb_substr($in->title, 0, 200), 'description' => $in->description, 'reasoning' => $in->reasoning, 'priority' => $in->priority,
                'target_entity_type' => $in->targetType, 'target_entity_id' => $targetId, 'payload' => $payload, 'source_data_snapshot' => $in->sourceData,
                'target_snapshot' => $snapshot, 'target_snapshot_hash' => $snapshot !== null ? SuggestionHasher::hash($snapshot) : null,
                'status' => S::PENDING, 'source' => $in->source, 'source_meta' => $meta ?: null,
                'created_by_token_id' => $actor->isToken() ? $actor->id : null, 'created_by_admin_id' => $actor->isAdmin() ? $actor->id : null,
                'version' => 1, 'content_hash' => $hash, 'submitted_at' => $in->draft ? null : now(),
                'expires_at' => $in->expiresAt ?: now()->addDays((int) config('marketing.suggestions.default_expiry_days', 60)),
            ]);
            $this->revision($s, $actor, [], 'created');
            $this->log($s, 'created', $actor, array_filter(['warnings' => $check['warnings'], 'force_reason' => $meta['force_reason'] ?? null, 'draft' => $in->draft ?: null]));

            return [$s, $check['warnings']];
        });
    }

    /** @return array{0: MarketingSuggestion, 1: list<string>} */
    public function update(MarketingSuggestion $s, SuggestionEditData $d, SuggestionActor $actor): array
    {
        return DB::transaction(function () use ($s, $d, $actor) {
            $s = $this->repo->lockForUpdate($s->id);
            $allowed = $actor->isToken() ? [S::PENDING, S::EDITED] : [S::PENDING, S::UNDER_REVIEW, S::EDITED, S::APPROVED, S::FAILED];
            if (! in_array($s->status, $allowed, true)) {
                throw SuggestionException::make('not_editable', 409, ['status' => $s->status->value]);
            }
            if ($actor->isToken() && (int) $s->created_by_token_id !== (int) $actor->id) {
                throw SuggestionException::make('not_owner', 403);
            }
            $h = $this->types->get($s->type);
            $warnings = [];
            $changes = [];
            if ($d->payload !== null) {
                $payload = $h->normalize($d->payload, $s->target_entity_type);
                $target = $h->resolveTarget($s->target_entity_type, $s->target_entity_id);
                $check = $h->check($payload, $target, $s);
                if ($check['errors']) {
                    throw SuggestionException::validation($check['errors']);
                }
                $warnings = $check['warnings'];
                if (SuggestionHasher::json($payload) !== SuggestionHasher::json((array) $s->payload)) {
                    $changes['payload'] = $payload;
                }
            }
            foreach (['title' => $d->title, 'priority' => $d->priority] as $k => $v) {
                if ($v !== null && $v !== '' && $v !== ($s->$k instanceof \BackedEnum ? $s->$k->value : $s->$k)) {
                    $changes[$k] = $v;
                }
            }
            foreach (['description' => $d->description, 'reasoning' => $d->reasoning] as $k => $v) {
                if ($v !== null && $v !== (string) $s->$k) {
                    $changes[$k] = $v === '' ? null : $v;
                }
            }
            if ($d->expiresAtSent && $d->expiresAt !== $s->expires_at?->format('Y-m-d H:i:s') && ! ($d->expiresAt === null && $s->expires_at === null)) {
                $changes['expires_at'] = $d->expiresAt;
            }
            if (! $changes) {
                return [$s, $warnings];
            }
            $diff = [];
            foreach ($changes as $k => $v) {
                $old = $s->$k instanceof \BackedEnum ? $s->$k->value : ($s->$k instanceof \DateTimeInterface ? $s->$k->format('Y-m-d H:i:s') : $s->$k);
                $diff[] = ['field' => $k, 'from' => $old, 'to' => $v];
            }
            $wasApproved = $s->status === S::APPROVED;
            $this->invalidateApprovals($s, 'edited');
            $next = $actor->isToken() ? $s->status : S::EDITED;
            if ($next !== $s->status) {
                SuggestionStateMachine::assert($s->status, $next);
            }
            $fill = $changes + ['version' => $s->version + 1, 'status' => $next];
            if (isset($changes['payload'])) {
                $fill['content_hash'] = SuggestionHasher::content($s->type->value, $s->target_entity_type, $s->target_entity_id, $changes['payload']);
            }
            if ($wasApproved) {
                $fill['approved_at'] = null;
                $fill['approved_by'] = null;
            }
            $this->repo->update($s, $fill);
            $this->revision($s, $actor, $diff, $d->note);
            $this->log($s, 'edited', $actor, ['version' => $s->version, 'fields' => array_keys($changes), 'approval_invalidated' => $wasApproved ?: null, 'warnings' => $warnings ?: null]);

            return [$s, $warnings];
        });
    }

    /** API: the token marks its draft ready for review (sets submitted_at). Idempotent. */
    public function submit(MarketingSuggestion $s, SuggestionActor $actor): MarketingSuggestion
    {
        if ($actor->isToken() && (int) $s->created_by_token_id !== (int) $actor->id) {
            throw SuggestionException::make('not_owner', 403);
        }
        if (! in_array($s->status, [S::PENDING, S::EDITED], true)) {
            throw SuggestionException::make('not_editable', 409, ['status' => $s->status->value]);
        }
        if ($s->submitted_at === null) {
            $this->repo->update($s, ['submitted_at' => now()]);
            $this->log($s, 'submitted', $actor);
        }

        return $s;
    }

    // ------------------------------------------------------------------ review

    public function startReview(MarketingSuggestion $s, Admin $admin): MarketingSuggestion
    {
        SuggestionStateMachine::assert($s->status, S::UNDER_REVIEW);
        $this->repo->update($s, ['status' => S::UNDER_REVIEW, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        $this->log($s, 'review_started', SuggestionActor::admin($admin));

        return $s;
    }

    /** Permissions needed to approve (or apply) this suggestion: the base one + the type's own (ai_seo / ai_blog / ai_theme .approve). */
    public function permissionsFor(MarketingSuggestion $s, string $base): array
    {
        return array_values(array_filter([$base, $this->types->get($s->type)->approvePermission()]));
    }

    private function authorize(MarketingSuggestion $s, Admin $admin, string $base): void
    {
        foreach ($this->permissionsFor($s, $base) as $p) {
            if (! $admin->can($p)) {
                throw SuggestionException::make('forbidden', 403, ['permission' => $p]);
            }
        }
    }

    public function approve(MarketingSuggestion $s, Admin $admin, ?string $note = null): MarketingSuggestionApproval
    {
        $this->authorize($s, $admin, 'ai_suggestions.approve');

        return DB::transaction(function () use ($s, $admin, $note) {
            $s = $this->repo->lockForUpdate($s->id);
            if ($s->submitted_at === null) {
                throw SuggestionException::make('not_submitted', 409);
            }
            if ($s->isExpired()) {
                throw SuggestionException::make('expired', 409);
            }
            SuggestionStateMachine::assert($s->status, S::APPROVED);
            $h = $this->types->get($s->type);
            $check = $h->check((array) $s->payload, $h->resolveTarget($s->target_entity_type, $s->target_entity_id), $s);
            if ($check['errors']) {
                throw SuggestionException::validation($check['errors']);
            }
            $this->invalidateApprovals($s, 'superseded');
            $a = MarketingSuggestionApproval::create(['suggestion_id' => $s->id, 'version' => $s->version, 'content_hash' => $s->content_hash, 'approved_by' => $admin->id,
                'decision' => 'approved', 'note' => $note ? mb_substr($note, 0, 1000) : null,
                'expires_at' => now()->addHours((int) config('marketing.suggestions.approval_valid_hours', 168))]);
            $this->repo->update($s, ['status' => S::APPROVED, 'approved_by' => $admin->id, 'approved_at' => now(), 'reviewed_by' => $s->reviewed_by ?? $admin->id,
                'reviewed_at' => $s->reviewed_at ?? now()]);
            $this->log($s, 'approved', SuggestionActor::admin($admin), ['version' => $s->version, 'content_hash' => $s->content_hash, 'note' => $note, 'warnings' => $check['warnings'] ?: null]);

            return $a;
        });
    }

    public function reject(MarketingSuggestion $s, Admin $admin, ?string $note = null): MarketingSuggestion
    {
        return DB::transaction(function () use ($s, $admin, $note) {
            $s = $this->repo->lockForUpdate($s->id);
            SuggestionStateMachine::assert($s->status, S::REJECTED);
            $this->invalidateApprovals($s, 'rejected');
            MarketingSuggestionApproval::create(['suggestion_id' => $s->id, 'version' => $s->version, 'content_hash' => $s->content_hash, 'approved_by' => $admin->id,
                'decision' => 'rejected', 'note' => $note ? mb_substr($note, 0, 1000) : null]);
            $this->repo->update($s, ['status' => S::REJECTED, 'rejected_at' => now(), 'reviewed_by' => $s->reviewed_by ?? $admin->id, 'reviewed_at' => $s->reviewed_at ?? now()]);
            $this->log($s, 'rejected', SuggestionActor::admin($admin), ['note' => $note]);

            return $s;
        });
    }

    public function archive(MarketingSuggestion $s, Admin $admin): MarketingSuggestion
    {
        SuggestionStateMachine::assert($s->status, S::ARCHIVED);
        $this->invalidateApprovals($s, 'archived');
        $this->repo->update($s, ['status' => S::ARCHIVED]);
        $this->log($s, 'archived', SuggestionActor::admin($admin));

        return $s;
    }

    /** Take a fresh snapshot of the target after a stale conflict: new version, approvals invalidated, back to `edited` (re-approval needed). */
    public function resnapshot(MarketingSuggestion $s, Admin $admin): MarketingSuggestion
    {
        return DB::transaction(function () use ($s, $admin) {
            $s = $this->repo->lockForUpdate($s->id);
            SuggestionStateMachine::assert($s->status, S::EDITED);
            $h = $this->types->get($s->type);
            $target = $h->resolveTarget($s->target_entity_type, $s->target_entity_id);
            if ($s->target_entity_type && ! $target) {
                throw SuggestionException::make('target_not_found', 409);
            }
            $snap = $h->snapshot($target, (array) $s->payload);
            $this->invalidateApprovals($s, 'resnapshot');
            $this->repo->update($s, ['target_snapshot' => $snap, 'target_snapshot_hash' => $snap !== null ? SuggestionHasher::hash($snap) : null,
                'version' => $s->version + 1, 'status' => S::EDITED, 'approved_at' => null, 'approved_by' => null]);
            $this->revision($s, SuggestionActor::admin($admin), [['field' => 'target_snapshot', 'from' => null, 'to' => $snap]], 'resnapshot');
            $this->log($s, 'resnapshotted', SuggestionActor::admin($admin), ['version' => $s->version]);

            return $s;
        });
    }

    // ------------------------------------------------------------------ apply / rollback

    /** Stale check: the target fields this suggestion touches still equal the snapshot taken when it was made. */
    public function staleness(MarketingSuggestion $s): ?array
    {
        if ($s->target_snapshot_hash === null) {
            return null;
        }
        $h = $this->types->get($s->type);
        $target = $h->resolveTarget($s->target_entity_type, $s->target_entity_id);
        $now = $h->snapshot($target, (array) $s->payload);

        return ($now === null || SuggestionHasher::hash($now) !== $s->target_snapshot_hash) ? ['snapshot' => $s->target_snapshot, 'current' => $now] : null;
    }

    /**
     * Apply the approved version. Same idempotency key ⇒ the same execution is returned (never re-run); an applied suggestion is never applied again.
     * Throws SuggestionException for refusals (no execution row); a failure inside the handler is recorded (execution `failed`, suggestion `failed`).
     */
    public function apply(MarketingSuggestion $s, Admin $admin, ?string $key = null, array $options = []): MarketingSuggestionExecution
    {
        $this->authorize($s, $admin, 'ai_suggestions.apply');
        $h = $this->types->get($s->type);
        if (! $h->applicable()) {
            throw SuggestionException::make('not_applicable', 422);
        }
        $key = $key ? mb_substr('k:'.$key, 0, 100) : 'apply:'.$s->id.':v'.$s->version.':'.substr($s->content_hash, 0, 16);
        if ($done = MarketingSuggestionExecution::query()->where('idempotency_key', $key)->first()) {
            return $done;
        }
        $options = array_intersect_key($options, $h->applyOptions());

        try {
            [$s, $exec, $target] = DB::transaction(function () use ($s, $admin, $key, $options, $h) {
                $s = $this->repo->lockForUpdate($s->id);
                if ($done = MarketingSuggestionExecution::query()->where('idempotency_key', $key)->first()) {
                    return [$s, $done, null];
                }
                if ($s->status === S::APPLIED) {
                    return [$s, $s->lastSuccessfulApply(), null];
                }
                if (! in_array($s->status, [S::APPROVED, S::FAILED], true)) {
                    throw SuggestionException::make('approval_required', 409, ['status' => $s->status->value]);
                }
                if (! $s->validApproval()) {
                    throw SuggestionException::make('approval_required', 409);
                }
                if ($s->isExpired()) {
                    throw SuggestionException::make('expired', 409);
                }
                $target = $h->resolveTarget($s->target_entity_type, $s->target_entity_id);
                if (($s->target_entity_type || $h->targetRequired((array) $s->payload)) && ! $target) {
                    throw SuggestionException::make('target_not_found', 409);
                }
                if ($stale = $this->staleness($s)) {
                    throw SuggestionException::make('stale_target', 409, $stale);   // logged as `conflict` below (outside the rolled-back transaction)
                }
                SuggestionStateMachine::assert($s->status, S::APPLYING);
                $this->repo->update($s, ['status' => S::APPLYING]);
                $exec = MarketingSuggestionExecution::create(['suggestion_id' => $s->id, 'kind' => 'apply', 'idempotency_key' => $key, 'status' => X::RUNNING,
                    'version' => $s->version, 'content_hash' => $s->content_hash, 'options' => $options ?: null, 'executed_by' => $admin->id, 'started_at' => now()]);

                return [$s, $exec, $target ?? false];
            });
        } catch (QueryException $e) {
            // a concurrent request inserted the same idempotency key first: return its execution
            if ($done = MarketingSuggestionExecution::query()->where('idempotency_key', $key)->first()) {
                return $done;
            }
            throw $e;
        } catch (SuggestionException $e) {
            if ($e->errorCode === 'stale_target') {
                $this->log($s, 'conflict', SuggestionActor::admin($admin), ['reason' => 'stale_target']);
            }
            throw $e;
        }
        if ($target === null) {
            return $exec;   // already done (idempotent replay)
        }

        try {
            $result = DB::transaction(fn () => $h->apply($s, $target ?: null, $options, $admin->id));
        } catch (\Throwable $e) {
            $msg = $e instanceof SuggestionException ? $e->getMessage().($e->errors ? ' '.json_encode($e->errors, JSON_UNESCAPED_UNICODE) : '') : __('marketing.suggestions.errors.apply_failed');
            $e instanceof SuggestionException || report($e);
            DB::transaction(function () use ($s, $exec, $admin, $msg, $e) {
                $exec->update(['status' => X::FAILED, 'error' => mb_substr($msg, 0, 5000), 'finished_at' => now()]);
                $this->repo->update($s, ['status' => S::FAILED, 'last_error' => mb_substr($msg, 0, 2000)]);
                $this->log($s, 'failed', SuggestionActor::admin($admin), ['execution_id' => $exec->id, 'error' => mb_substr($msg, 0, 500), 'exception' => $e instanceof SuggestionException ? $e->errorCode : class_basename($e)]);
            });

            return $exec->refresh();
        }
        DB::transaction(function () use ($s, $exec, $admin, $result) {
            $exec->update(['status' => X::SUCCEEDED, 'before' => $result->before, 'after' => $result->after, 'warnings' => $result->warnings ?: null, 'finished_at' => now()]);
            $this->repo->update($s, ['status' => S::APPLIED, 'applied_by' => $admin->id, 'applied_at' => now(), 'last_error' => null,
                'result_entity_type' => $result->resultType, 'result_entity_id' => $result->resultId]);
            $this->log($s, 'applied', SuggestionActor::admin($admin), ['execution_id' => $exec->id, 'result' => $result->resultType ? $result->resultType.'#'.$result->resultId : null]);
        });

        return $exec->refresh();
    }

    public function rollback(MarketingSuggestion $s, Admin $admin): MarketingSuggestionExecution
    {
        $this->authorize($s, $admin, 'ai_suggestions.apply');
        $h = $this->types->get($s->type);
        if (! $h->supportsRollback()) {
            throw SuggestionException::make('rollback_not_supported', 422);
        }

        return DB::transaction(function () use ($s, $admin, $h) {
            $s = $this->repo->lockForUpdate($s->id);
            $applied = $s->lastSuccessfulApply();
            if ($s->status !== S::APPLIED || ! $applied) {
                if ($s->rolled_back_at && ($done = MarketingSuggestionExecution::query()->where('idempotency_key', 'rollback:'.$applied?->id)->first())) {
                    return $done;
                }
                throw SuggestionException::make('illegal_transition', 409, ['from' => $s->status->value, 'to' => 'rolled_back'], ['from' => $s->status->label(), 'to' => __('marketing.suggestions.rolled_back')]);
            }
            $restored = $h->rollback($s, $applied, $admin->id);
            $exec = MarketingSuggestionExecution::create(['suggestion_id' => $s->id, 'kind' => 'rollback', 'idempotency_key' => 'rollback:'.$applied->id, 'status' => X::SUCCEEDED,
                'version' => $s->version, 'content_hash' => $s->content_hash, 'before' => $applied->after, 'after' => $restored, 'executed_by' => $admin->id,
                'rollback_of' => $applied->id, 'started_at' => now(), 'finished_at' => now()]);
            SuggestionStateMachine::assert($s->status, S::ARCHIVED);
            $this->repo->update($s, ['status' => S::ARCHIVED, 'rolled_back_at' => now()]);
            $this->log($s, 'rolled_back', SuggestionActor::admin($admin), ['execution_id' => $exec->id, 'of' => $applied->id]);

            return $exec;
        });
    }

    /** Blog: publish now ($at null) or schedule ($at future) the draft created by apply. Needs ai_blog.approve (checked by the caller + here). */
    public function publishBlog(MarketingSuggestion $s, Admin $admin, ?\DateTimeInterface $at, ?string $key = null): MarketingSuggestionExecution
    {
        $this->authorize($s, $admin, 'ai_suggestions.apply');
        $h = $this->types->get($s->type);
        if (! $h instanceof BlogSuggestionType || $s->status !== S::APPLIED || ! $s->result_entity_id) {
            throw SuggestionException::make('blog_not_applied', 409);
        }
        if ($at && $at <= now()) {
            throw SuggestionException::validation(['publish_at' => __('marketing.suggestions.errors.schedule_past')]);
        }
        $kind = $at ? 'schedule' : 'publish';
        $key = $key ? mb_substr('k:'.$key, 0, 100) : $kind.':'.$s->id.':'.($at ? $at->format('YmdHis') : now()->format('YmdHi'));
        if ($done = MarketingSuggestionExecution::query()->where('idempotency_key', $key)->first()) {
            return $done;
        }

        return DB::transaction(function () use ($s, $admin, $at, $kind, $key, $h) {
            $r = $h->publish($s, $at);
            $exec = MarketingSuggestionExecution::create(['suggestion_id' => $s->id, 'kind' => $kind, 'idempotency_key' => $key, 'status' => X::SUCCEEDED, 'version' => $s->version,
                'content_hash' => $s->content_hash, 'before' => $r['before'], 'after' => $r['after'], 'executed_by' => $admin->id, 'started_at' => now(), 'finished_at' => now()]);
            $this->log($s, $kind === 'publish' ? 'published' : 'scheduled', SuggestionActor::admin($admin), ['execution_id' => $exec->id, 'published_at' => $r['after']['published_at']]);

            return $exec;
        });
    }

    /** Scheduler: open suggestions past expires_at → expired. @return int count */
    public function expireDue(): int
    {
        $n = 0;
        foreach ($this->repo->dueForExpiry() as $s) {
            if (SuggestionStateMachine::can($s->status, S::EXPIRED)) {
                $this->invalidateApprovals($s, 'expired');
                $this->repo->update($s, ['status' => S::EXPIRED]);
                $this->log($s, 'expired', SuggestionActor::system());
                $n++;
            }
        }

        return $n;
    }

    // ------------------------------------------------------------------ views

    public function compare(MarketingSuggestion $s): array
    {
        $h = $this->types->get($s->type);

        return $h->compare($s, $h->resolveTarget($s->target_entity_type, $s->target_entity_id));
    }

    public function warnings(MarketingSuggestion $s): array
    {
        $h = $this->types->get($s->type);

        return $h->check((array) $s->payload, $h->resolveTarget($s->target_entity_type, $s->target_entity_id), $s);
    }

    public function target(MarketingSuggestion $s): ?Model
    {
        return $this->types->get($s->type)->resolveTarget($s->target_entity_type, $s->target_entity_id);
    }

    // ------------------------------------------------------------------ internals

    private function invalidateApprovals(MarketingSuggestion $s, string $reason): void
    {
        MarketingSuggestionApproval::query()->where('suggestion_id', $s->id)->where('decision', 'approved')->whereNull('invalidated_at')
            ->update(['invalidated_at' => now(), 'invalidated_reason' => $reason]);
    }

    private function revision(MarketingSuggestion $s, SuggestionActor $actor, array $diff, ?string $note): void
    {
        MarketingSuggestionRevision::create(['suggestion_id' => $s->id, 'version' => $s->version, 'title' => $s->title, 'payload' => $s->payload, 'content_hash' => $s->content_hash,
            'editor_type' => $actor->type, 'editor_id' => $actor->id, 'diff' => $diff ?: null, 'note' => $note ? mb_substr($note, 0, 500) : null, 'created_at' => now()]);
    }

    private function log(MarketingSuggestion $s, string $action, SuggestionActor $actor, array $meta = []): void
    {
        MarketingSuggestionActivityLog::create(['suggestion_id' => $s->id, 'action' => $action, 'actor_type' => $actor->type, 'actor_id' => $actor->id,
            'meta' => array_filter($meta, fn ($v) => $v !== null) ?: null, 'created_at' => now()]);
    }
}
