<?php

namespace App\Services\Growth;

use App\Models\GrowthApproval;
use App\Models\SmallBatchSignup;
use App\Models\SmallBatchTest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Small-Batch Launch Lab (spec 16.4): test demand with an interest page / survey / limited stock BEFORE a big purchase. A pre-order needs written, visible terms
 * and an explicit acceptance per signup. Contacts are stored only as a hash + masked form. The decision gate recommends "reorder" only after the target and the
 * minimum duration; a reorder decision is a human approval and never creates a purchase.
 */
class SmallBatchService
{
    public const MODES = ['interest', 'survey', 'limited_stock'];

    public function __construct(private ApprovalService $approvals, private ActionLogger $log) {}

    public function create(array $d, ?int $adminId): SmallBatchTest
    {
        $this->validateTerms($d);
        do {
            $code = Str::lower(Str::random(8));
        } while (SmallBatchTest::withoutGlobalScopes()->where('code', $code)->exists());
        $t = SmallBatchTest::create(collect($d)->only(['name', 'product_id', 'mode', 'description', 'survey_question', 'preorder', 'preorder_terms', 'limited_stock_qty', 'target_signups', 'starts_on', 'ends_on'])->all() + ['code' => $code, 'preorder' => (bool) ($d['preorder'] ?? false), 'status' => 'draft']);
        $this->log->log('smallbatch.created', 'admin', $adminId, null, $t);

        return $t;
    }

    private function validateTerms(array $d): void
    {
        $err = [];
        if (! empty($d['preorder']) && mb_strlen(trim((string) ($d['preorder_terms'] ?? ''))) < 30) {
            $err['preorder_terms'] = __('growth_hub.batch.terms_required');
        }
        if (($d['mode'] ?? '') === 'limited_stock' && (int) ($d['limited_stock_qty'] ?? 0) < 1) {
            $err['limited_stock_qty'] = __('growth_hub.batch.qty_required');
        }
        if ($err) {
            throw ValidationException::withMessages($err);
        }
    }

    public function open(SmallBatchTest $t, ?int $adminId): SmallBatchTest
    {
        $this->validateTerms($t->only(['preorder', 'preorder_terms', 'mode', 'limited_stock_qty']));
        $t->update(['status' => 'running', 'opened_at' => $t->opened_at ?? now()]);
        $this->log->log('smallbatch.opened', 'admin', $adminId, null, $t);

        return $t;
    }

    public static function mask(string $contact): string
    {
        $c = trim($contact);
        if (str_contains($c, '@')) {
            [$u, $dom] = explode('@', $c, 2);

            return mb_substr($u, 0, 1).'***@'.$dom;
        }

        return '***'.mb_substr(preg_replace('/\D/', '', $c), -3);
    }

    /** Idempotent per contact: a repeat signup returns the first one. @return array{0:SmallBatchSignup,1:bool} */
    public function signup(SmallBatchTest $t, array $d): array
    {
        $open = $t->status === 'running' && (! $t->ends_on || ! $t->ends_on->isPast()) && (! $t->starts_on || ! $t->starts_on->isFuture());
        if (! $open) {
            throw ValidationException::withMessages(['status' => __('growth_hub.batch.closed')]);
        }
        if (empty($d['consent'])) {
            throw ValidationException::withMessages(['consent' => __('growth_hub.batch.consent_required')]);
        }
        if ($t->preorder && empty($d['preorder_accepted'])) {
            throw ValidationException::withMessages(['preorder_accepted' => __('growth_hub.batch.accept_terms')]);
        }
        $hash = sha1($t->tenant_id.'|'.$t->id.'|'.mb_strtolower(trim($d['contact'])));
        if ($ex = SmallBatchSignup::where('small_batch_test_id', $t->id)->where('contact_hash', $hash)->first()) {
            return [$ex, false];
        }
        $qty = max(1, (int) ($d['quantity'] ?? 1));
        if ($t->mode === 'limited_stock' && (int) SmallBatchSignup::where('small_batch_test_id', $t->id)->sum('quantity') + $qty > (int) $t->limited_stock_qty) {
            throw ValidationException::withMessages(['quantity' => __('growth_hub.batch.sold_out')]);
        }
        $s = SmallBatchSignup::create(['small_batch_test_id' => $t->id, 'contact_hash' => $hash, 'contact_masked' => self::mask($d['contact']), 'answer' => isset($d['answer']) ? mb_substr(PiiScrubber::text((string) $d['answer']), 0, 300) : null,
            'quantity' => $qty, 'preorder_accepted' => $t->preorder, 'consent' => true]);

        return [$s, true];
    }

    public function stats(SmallBatchTest $t): array
    {
        $signups = SmallBatchSignup::where('small_batch_test_id', $t->id);
        $n = (int) (clone $signups)->count();
        $days = $t->opened_at ? (int) $t->opened_at->diffInDays(now()) : 0;
        $minDays = (int) config('growth.lab.smallbatch_min_days');
        $reasons = array_values(array_filter([$n < $t->target_signups ? 'target' : null, $days < $minDays ? 'min_days' : null]));

        return ['signups' => $n, 'units' => (int) (clone $signups)->sum('quantity'), 'target' => (int) $t->target_signups, 'days_open' => $days, 'min_days' => $minDays,
            'gate' => $reasons ? 'not_ready' : 'ready', 'reasons' => $reasons];
    }

    public function decide(SmallBatchTest $t, string $decision, ?string $note, ?int $adminId): SmallBatchTest|GrowthApproval
    {
        if ($decision === 'reorder') {
            $s = $this->stats($t);
            if ($s['gate'] !== 'ready') {
                throw ValidationException::withMessages(['decision' => __('growth_hub.batch.gate_not_ready')]);
            }

            return $this->approvals->request('batch_reorder', __('growth_hub.batch.approval_title', ['name' => $t->name]), ['test_id' => $t->id, 'signups' => $s['signups'], 'target' => $s['target'], 'note' => $note], $t, $adminId ? 'admin:'.$adminId : 'system');
        }
        $t->update(['decision' => $decision, 'decision_note' => $note ? mb_substr($note, 0, 300) : null, 'status' => 'closed']);
        $this->log->log('smallbatch.decision', 'admin', $adminId, $note, $t, 'ok', ['decision' => $decision]);

        return $t;
    }

    /** Approval handler: records the decision only. A purchase order is created by a human in the purchasing module. */
    public function materializeDecision(GrowthApproval $a): array
    {
        $t = SmallBatchTest::findOrFail($a->payload['test_id']);
        if ($this->stats($t)['gate'] !== 'ready') {
            throw ValidationException::withMessages(['decision' => __('growth_hub.batch.gate_not_ready')]);
        }
        $t->update(['decision' => 'reorder', 'decision_note' => $a->payload['note'] ?? null, 'status' => 'closed']);

        return ['test_id' => $t->id, 'decision' => 'reorder'];
    }
}
