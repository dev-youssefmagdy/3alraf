<?php

namespace App\Services\Growth;

use App\Models\Affiliate;
use App\Models\Ambassador;
use App\Models\Customer;
use App\Models\ExperimentAdDay;
use App\Models\GrowthActionLog;
use App\Models\Interaction;
use App\Models\SmallBatchSignup;
use App\Models\SmallBatchTest;

/** Retention + erasure for personal/operational growth data (N4/N5 gaps). Global scopes (soft deletes) are bypassed on purpose. */
class GrowthRetentionService
{
    /** @return array<string,int> deleted rows per table */
    public function prune(bool $dry = false): array
    {
        $r = (array) config('growth.retention');
        $q = fn ($model, string $col, int $days) => $model::withoutGlobalScopes()->where($col, '<', now()->subDays($days));
        $out = [];
        foreach ([
            'interactions' => [Interaction::class, 'created_at', $r['interactions_days'] ?? 180],
            'small_batch_signups' => [SmallBatchSignup::class, 'created_at', $r['small_batch_days'] ?? 180],
            'experiment_ad_days' => [ExperimentAdDay::class, 'day', $r['ad_days_days'] ?? 730],
        ] as $k => [$m, $c, $d]) {
            $b = $q($m, $c, (int) $d);
            $out[$k] = $dry ? $b->count() : $b->delete();
        }
        $b = GrowthActionLog::withoutGlobalScopes()->where('created_at', '<', now()->subDays((int) ($r['action_log_days'] ?? 730)));
        $out['growth_action_log'] = $dry ? $b->count() : $b->delete();
        $out['growth_events'] = $dry ? 0 : app(GrowthEventService::class)->prune();
        $out['idempotency'] = $dry ? 0 : app(IdempotencyService::class)->prune();

        return $out;
    }

    /** Erase one data subject: interactions of a thread/external id and small-batch signups matching a contact (hash re-derived per test). */
    public function erase(?string $thread = null, ?string $contact = null): array
    {
        $out = ['interactions' => 0, 'small_batch_signups' => 0];
        if ($thread) {
            $out['interactions'] = Interaction::withoutGlobalScopes()->where(fn ($b) => $b->where('thread_id', $thread)->orWhere('external_id', $thread))->delete();
        }
        if ($contact) {
            $hashes = $this->signupHashes([mb_strtolower(trim($contact))]);
            $out['small_batch_signups'] = $hashes ? SmallBatchSignup::withoutGlobalScopes()->whereIn('contact_hash', $hashes)->delete() : 0;
        }
        GrowthActionLog::withoutGlobalScopes()->create(['action' => 'data_erasure', 'source' => 'system', 'outcome' => 'ok', 'meta' => $out, 'created_at' => now()]);

        return $out;
    }

    /**
     * Customer deletion / anonymisation hook: erases what growth holds about this customer (interactions linked at ingest, small-batch signups by email/phone hash,
     * ambassador free text of an affiliate with the same email/phone). Audited as `data_erasure`.
     */
    public function eraseCustomer(Customer $c): array
    {
        $out = ['interactions' => Interaction::withoutGlobalScopes()->where('customer_id', $c->id)->delete(), 'small_batch_signups' => 0, 'ambassadors' => 0];
        $email = $c->email ? mb_strtolower(trim($c->email)) : null;
        $phone = $c->phone ? mb_strtolower(trim($c->phone)) : null;
        $hashes = $this->signupHashes(array_filter([$email, $phone]));
        $out['small_batch_signups'] = $hashes ? SmallBatchSignup::withoutGlobalScopes()->whereIn('contact_hash', $hashes)->delete() : 0;
        $aff = Affiliate::withoutGlobalScopes()->where(fn ($q) => $q->when($email, fn ($b) => $b->orWhere('email', $email))->when($phone, fn ($b) => $b->orWhere('phone', $phone)))->pluck('id');
        if ($aff->isNotEmpty()) {
            $out['ambassadors'] = Ambassador::withoutGlobalScopes()->whereIn('affiliate_id', $aff)->update(['disclosure' => null, 'reward_rule' => null, 'status' => 'inactive']);
        }
        GrowthActionLog::withoutGlobalScopes()->create(['action' => 'data_erasure', 'source' => 'system', 'outcome' => 'ok', 'meta' => ['customer_id' => $c->id] + $out, 'created_at' => now()]);

        return $out;
    }

    /**
     * Signup contact hashes of every small-batch test for these normalised contacts: current format sha1("{test}|{contact}")
     * (SmallBatchService) plus the format of rows written before the single-store conversion, sha1("1|{test}|{contact}").
     *
     * @param  array<int,string>  $contacts
     * @return array<int,string>
     */
    private function signupHashes(array $contacts): array
    {
        if (! $contacts) {
            return [];
        }

        return SmallBatchTest::withoutGlobalScopes()->pluck('id')
            ->flatMap(fn ($id) => collect($contacts)->flatMap(fn ($x) => [sha1($id.'|'.$x), sha1('1|'.$id.'|'.$x)]))->all();
    }
}
