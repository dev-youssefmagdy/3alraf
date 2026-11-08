<?php

namespace App\Services\Marketing\Meta;

use App\DTOs\Marketing\MetaCampaignDraftData;
use App\Enums\Marketing\MediaAssetStatus;
use App\Enums\Marketing\MetaDraftStatus as S;
use App\Models\Admin;
use App\Models\MetaCampaignApproval;
use App\Models\MetaCampaignDraft;
use App\Models\Product;
use App\Models\SocialMediaAsset;
use App\Repositories\Contracts\MetaCampaignDraftRepositoryInterface;
use App\Services\Marketing\MarketingActionException as E;
use App\Services\Marketing\Suggestions\SuggestionActor;
use App\Services\Marketing\Suggestions\SuggestionHasher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Meta campaign drafts + their server-side approval (P6, MCP.md §7 "approval requirements", §8).
 * - create / update (admin or API token; a token edits only its own draft in draft / pending_approval / rejected);
 * - content hash over every field that will be sent to Meta; any content change ⇒ version + 1, approvals invalidated, back to `draft`;
 * - submit ⇒ approval request (approval row `pending` bound to version + hash), status pending_approval;
 * - approve / reject: panel only, permission meta_ads.approve, re-hash of the stored content must equal the bound hash (tamper check),
 *   approval valid for meta.approval_valid_hours;
 * - content is frozen as soon as anything exists in Meta (hasMetaObjects).
 * Publishing is MetaPublisher (meta_ads.publish). Everything is written to meta_audit_logs.
 */
class MetaCampaignDraftService
{
    public const HASHED = ['name', 'objective', 'product_ids', 'product_urls', 'audience', 'locations', 'placements', 'budget_type', 'budget_amount', 'currency',
        'start_at', 'end_at', 'creative', 'copy', 'cta', 'landing_url', 'optimization_goal', 'expected_kpis', 'risks', 'assumptions'];

    public function __construct(private MetaCampaignDraftRepositoryInterface $repo, private MetaAudit $audit) {}

    public function repo(): MetaCampaignDraftRepositoryInterface
    {
        return $this->repo;
    }

    /** Content hash (what an approval binds to). Recomputed from the stored columns, so a tampered row never matches its approval. */
    public static function hash(array|MetaCampaignDraft $d): string
    {
        $c = $d instanceof MetaCampaignDraft ? self::columnsOf($d) : $d;

        return SuggestionHasher::hash(array_intersect_key($c, array_flip(self::HASHED)));
    }

    public static function columnsOf(MetaCampaignDraft $d): array
    {
        $out = [];
        foreach (self::HASHED as $k) {
            $v = $d->getAttribute($k);
            $out[$k] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : ($k === 'budget_amount' ? number_format((float) $v, 2, '.', '') : $v);
        }

        return $out;
    }

    // ------------------------------------------------------------------ create / edit / submit

    /** @return array{0: MetaCampaignDraft, 1: list<string>} */
    public function create(MetaCampaignDraftData $data, SuggestionActor $actor, bool $submit = false): array
    {
        $cols = $this->withUrls($data->content);
        $check = $this->check($cols);
        if ($check['errors']) {
            throw E::validation($check['errors']);
        }

        return DB::transaction(function () use ($cols, $actor, $submit, $check) {
            $d = $this->repo->create($cols + ['status' => S::DRAFT, 'version' => 1, 'content_hash' => self::hash($cols),
                'created_by_admin_id' => $actor->isAdmin() ? $actor->id : null, 'created_by_token_id' => $actor->isToken() ? $actor->id : null]);
            $this->audit->log($d->id, 'draft_created', $actor, ['version' => 1, 'content_hash' => $d->content_hash, 'warnings' => $check['warnings'] ?: null]);
            if ($submit) {
                $d = $this->submit($d, $actor);
            }

            return [$d, $check['warnings']];
        });
    }

    /** Partial update: $input = the sent top-level fields (merged into the stored content). */
    public function update(MetaCampaignDraft $d, array $input, SuggestionActor $actor): array
    {
        return DB::transaction(function () use ($d, $input, $actor) {
            $d = $this->repo->lockForUpdate($d->id);
            $this->assertEditable($d, $actor);
            $cols = $this->withUrls(MetaCampaignDraftData::fromArray(array_replace(MetaCampaignDraftData::inputOf(self::columnsOf($d)), $input))->content);
            $check = $this->check($cols, $d);
            if ($check['errors']) {
                throw E::validation($check['errors']);
            }
            $hash = self::hash($cols);
            if ($hash === $d->content_hash) {
                return [$d, $check['warnings']];
            }
            $old = self::columnsOf($d);
            $changed = array_keys(array_filter($cols, fn ($v, $k) => SuggestionHasher::json($v) !== SuggestionHasher::json($old[$k] ?? null), ARRAY_FILTER_USE_BOTH));
            $wasApproved = $d->status === S::APPROVED;
            $invalidated = $this->invalidate($d, 'edited');
            $this->repo->update($d, $cols + ['version' => $d->version + 1, 'content_hash' => $hash, 'status' => S::DRAFT, 'submitted_at' => null,
                'approved_by' => null, 'approved_at' => null, 'last_error' => null]);
            $this->audit->log($d->id, 'draft_updated', $actor, ['version' => $d->version, 'content_hash' => $hash, 'fields' => $changed,
                'approval_invalidated' => ($wasApproved || $invalidated) ?: null, 'warnings' => $check['warnings'] ?: null]);

            return [$d, $check['warnings']];
        });
    }

    public function submit(MetaCampaignDraft $d, SuggestionActor $actor): MetaCampaignDraft
    {
        if ($actor->isToken() && (int) $d->created_by_token_id !== (int) $actor->id) {
            throw E::make('meta', 'not_owner', 403);
        }
        if ($d->status === S::PENDING_APPROVAL) {
            return $d;   // idempotent
        }
        if (! in_array($d->status, [S::DRAFT, S::REJECTED], true)) {
            throw E::make('meta', 'illegal_transition', 409, ['status' => $d->status->value]);
        }
        $check = $this->check(self::columnsOf($d), $d);
        if ($check['errors']) {
            throw E::validation($check['errors']);
        }

        return DB::transaction(function () use ($d, $actor) {
            $this->invalidate($d, 'resubmitted');
            MetaCampaignApproval::create(['draft_id' => $d->id, 'draft_version' => $d->version, 'content_hash' => $d->content_hash, 'approval_status' => 'pending',
                'requested_by' => $actor->isAdmin() ? $actor->id : null, 'requested_by_token_id' => $actor->isToken() ? $actor->id : null]);
            $this->repo->update($d, ['status' => S::PENDING_APPROVAL, 'submitted_at' => now()]);
            $this->audit->log($d->id, 'approval_requested', $actor, ['version' => $d->version, 'content_hash' => $d->content_hash]);

            return $d;
        });
    }

    // ------------------------------------------------------------------ approval (panel only)

    public function approve(MetaCampaignDraft $d, Admin $admin, ?string $note = null): MetaCampaignApproval
    {
        if (! $admin->can('meta_ads.approve')) {
            throw E::make('meta', 'forbidden', 403, ['permission' => 'meta_ads.approve']);
        }

        return DB::transaction(function () use ($d, $admin, $note) {
            $d = $this->repo->lockForUpdate($d->id);
            if ($d->status !== S::PENDING_APPROVAL) {
                throw E::make('meta', 'illegal_transition', 409, ['status' => $d->status->value]);
            }
            if (self::hash($d) !== $d->content_hash) {
                $this->audit->log($d->id, 'hash_mismatch', SuggestionActor::admin($admin), ['stored' => $d->content_hash]);
                throw E::make('meta', 'hash_mismatch', 409);
            }
            $check = $this->check(self::columnsOf($d), $d, true);
            if ($check['errors']) {
                throw E::validation($check['errors']);
            }
            $req = MetaCampaignApproval::query()->where('draft_id', $d->id)->where('approval_status', 'pending')->whereNull('invalidated_at')
                ->where('draft_version', $d->version)->where('content_hash', $d->content_hash)->latest('id')->first();
            $fill = ['approval_status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now(), 'note' => $note ? mb_substr(trim($note), 0, 1000) : null,
                'expires_at' => now()->addHours(max(1, (int) config('meta.approval_valid_hours', 72)))];
            $a = $req ? tap($req)->update($fill) : MetaCampaignApproval::create($fill + ['draft_id' => $d->id, 'draft_version' => $d->version, 'content_hash' => $d->content_hash]);
            $this->repo->update($d, ['status' => S::APPROVED, 'approved_by' => $admin->id, 'approved_at' => now()]);
            $this->audit->log($d->id, 'approved', SuggestionActor::admin($admin), ['approval_id' => $a->id, 'version' => $d->version, 'content_hash' => $d->content_hash,
                'expires_at' => $a->expires_at?->toIso8601String(), 'note' => $note, 'warnings' => $check['warnings'] ?: null]);

            return $a;
        });
    }

    public function reject(MetaCampaignDraft $d, Admin $admin, ?string $note = null): MetaCampaignDraft
    {
        if (! $admin->can('meta_ads.approve')) {
            throw E::make('meta', 'forbidden', 403, ['permission' => 'meta_ads.approve']);
        }
        if (! in_array($d->status, [S::PENDING_APPROVAL, S::APPROVED], true)) {
            throw E::make('meta', 'illegal_transition', 409, ['status' => $d->status->value]);
        }

        return DB::transaction(function () use ($d, $admin, $note) {
            $this->invalidate($d, 'rejected');
            MetaCampaignApproval::create(['draft_id' => $d->id, 'draft_version' => $d->version, 'content_hash' => $d->content_hash, 'approval_status' => 'rejected',
                'approved_by' => $admin->id, 'note' => $note ? mb_substr(trim($note), 0, 1000) : null]);
            $this->repo->update($d, ['status' => S::REJECTED, 'approved_by' => null, 'approved_at' => null]);
            $this->audit->log($d->id, 'rejected', SuggestionActor::admin($admin), ['version' => $d->version, 'note' => $note]);

            return $d;
        });
    }

    public function archive(MetaCampaignDraft $d, Admin $admin): MetaCampaignDraft
    {
        if (in_array($d->status, [S::PUBLISHING, S::ARCHIVED], true)) {
            throw E::make('meta', 'illegal_transition', 409, ['status' => $d->status->value]);
        }
        $this->invalidate($d, 'archived');
        $this->repo->update($d, ['status' => S::ARCHIVED]);
        $this->audit->log($d->id, 'archived', SuggestionActor::admin($admin));

        return $d;
    }

    // ------------------------------------------------------------------ business checks

    /**
     * @return array{errors: array<string,string>, warnings: list<string>}
     * $forApproval: creatives must be APPROVED library items, the Facebook page must be configured.
     */
    public function check(array $c, ?MetaCampaignDraft $d = null, bool $forApproval = false): array
    {
        $e = [];
        $w = [];
        $t = fn ($k, $r = []) => __('marketing.meta.errors.'.$k, $r);
        if (($c['name'] ?? '') === '') {
            $e['name'] = __('validation.required', ['attribute' => __('marketing.meta.fields.name')]);
        }
        if (! array_key_exists($c['objective'] ?? '', (array) config('meta.objectives'))) {
            $e['objective'] = $t('objective');
        }
        $ids = (array) ($c['product_ids'] ?? []);
        $products = Product::query()->whereIn('id', $ids)->get(['id', 'slug', 'is_active', 'price']);
        if (! $ids) {
            $e['product_ids'] = $t('products_required');
        } elseif ($products->count() !== count($ids)) {
            $e['product_ids'] = $t('products_missing');
        } elseif ($products->contains(fn ($p) => ! $p->is_active || $p->price === null)) {
            $w[] = $t('products_inactive');
        }
        if ($ids && ($c['landing_url'] ?? null) === null && ! isset($e['product_ids'])) {
            $e['landing_url'] = $t('landing_url');   // Meta needs a destination: never publish a creative without one
        }
        if (($c['copy']['primary_text'] ?? null) === null) {
            $e['copy.primary_text'] = __('validation.required', ['attribute' => __('marketing.meta.fields.primary_text')]);
        }
        $amount = (float) ($c['budget_amount'] ?? 0);
        $start = $c['start_at'] ? CarbonImmutable::parse($c['start_at']) : null;
        $end = ($c['end_at'] ?? null) ? CarbonImmutable::parse($c['end_at']) : null;
        if (! $start) {
            $e['start_at'] = __('validation.required', ['attribute' => __('marketing.meta.fields.start_at')]);
        } elseif ($start->lt(now()->subMinutes(5)) && (! $d || ! $d->hasMetaObjects())) {
            if ($forApproval) {
                $e['start_at'] = $t('start_past');
            } else {
                $w[] = $t('start_past');
            }
        }
        if ($end && $start && $end->lte($start)) {
            $e['end_at'] = $t('end_before_start');
        }
        if (($c['budget_type'] ?? 'daily') === 'daily') {
            if ($amount < (float) config('meta.min_daily_budget') || $amount > (float) config('meta.max_daily_budget')) {
                $e['budget_amount'] = $t('daily_budget', ['min' => config('meta.min_daily_budget'), 'max' => config('meta.max_daily_budget')]);
            }
        } else {
            if (! $end) {
                $e['end_at'] = $t('lifetime_needs_end');
            } elseif ($start) {
                $days = max(1, (int) ceil($start->diffInHours($end) / 24));
                if ($amount < $days * (float) config('meta.min_daily_budget') || $amount > (float) config('meta.max_lifetime_budget')) {
                    $e['budget_amount'] = $t('lifetime_budget', ['min' => $days * (float) config('meta.min_daily_budget'), 'max' => config('meta.max_lifetime_budget')]);
                }
            }
        }
        $aud = (array) ($c['audience'] ?? []);
        if (($aud['age_min'] ?? 18) > ($aud['age_max'] ?? 65)) {
            $e['audience.age_min'] = $t('ages');
        }
        if (($aud['age_min'] ?? 18) < 18) {
            $w[] = $t('minors');
        }
        if (($aud['interests'] ?? []) || ($c['locations']['cities'] ?? [])) {
            $w[] = $t('targeting_notes');
        }
        $pl = (array) ($c['placements'] ?? []);
        if (! ($pl['automatic'] ?? true) && ! ($pl['publisher_platforms'] ?? [])) {
            $e['placements.publisher_platforms'] = $t('platforms_required');
        }
        $assetIds = (array) ($c['creative']['asset_ids'] ?? []);
        if ($assetIds) {
            $assets = SocialMediaAsset::query()->whereIn('id', $assetIds)->get(['id', 'product_id', 'status', 'mime', 'path', 'kind']);
            if ($assets->count() !== count($assetIds)) {
                $e['creative.asset_ids'] = $t('assets_missing');
            } else {
                if ($assets->contains(fn ($a) => ! in_array($a->product_id, $ids, true))) {
                    $w[] = $t('assets_other_product');
                }
                if ($assets->contains(fn ($a) => $a->status !== MediaAssetStatus::APPROVED)) {
                    if ($forApproval) {
                        $e['creative.asset_ids'] = $t('assets_not_approved');
                    } else {
                        $w[] = $t('assets_not_approved');
                    }
                }
                if ($assets->contains(fn ($a) => $a->isVideo())) {
                    $w[] = $t('video_creative');
                }
            }
        } else {
            $w[] = $t('no_creative');
        }
        if ($forApproval && (string) config('meta.page_id') === '') {
            $w[] = $t('page_missing');
        }

        return ['errors' => $e, 'warnings' => array_values(array_unique($w))];
    }

    // ------------------------------------------------------------------ internals

    private function assertEditable(MetaCampaignDraft $d, SuggestionActor $actor): void
    {
        if ($d->hasMetaObjects()) {
            throw E::make('meta', 'frozen', 409);
        }
        $allowed = $actor->isToken() ? [S::DRAFT, S::PENDING_APPROVAL, S::REJECTED] : [S::DRAFT, S::PENDING_APPROVAL, S::APPROVED, S::REJECTED, S::FAILED];
        if (! in_array($d->status, $allowed, true)) {
            throw E::make('meta', 'not_editable', 409, ['status' => $d->status->value]);
        }
        if ($actor->isToken() && (int) $d->created_by_token_id !== (int) $actor->id) {
            throw E::make('meta', 'not_owner', 403);
        }
    }

    /** Product URLs (default locale, on APP_URL whatever host the call came through) derived from product_ids; landing page defaults to the first product. */
    private function withUrls(array $c): array
    {
        $locale = (string) (setting('default_locale') ?: 'ar');
        $slugs = Product::query()->whereIn('id', (array) $c['product_ids'])->pluck('slug', 'id');
        $paths = [];
        foreach ((array) $c['product_ids'] as $id) {
            if (isset($slugs[$id])) {
                $paths[] = rescue(fn () => route('storefront.product.show', ['locale' => $locale, 'slug' => $slugs[$id]], false), null, false);
            }
        }
        $paths = array_values(array_filter($paths));
        $base = rtrim((string) config('app.url'), '/');
        $c['product_urls'] = array_map(fn ($p) => $base.$p, $paths);
        if (! $c['landing_url'] && $paths) {
            $c['landing_url'] = \App\Support\Marketing\InternalLink::normalize($paths[0]);
        }

        return $c;
    }

    /** Mark open approvals (pending / approved, not yet used) invalidated. @return bool any invalidated */
    private function invalidate(MetaCampaignDraft $d, string $reason): bool
    {
        return MetaCampaignApproval::query()->where('draft_id', $d->id)->whereIn('approval_status', ['pending', 'approved'])->whereNull('invalidated_at')
            ->where(fn ($q) => $q->whereNull('execution_status')->orWhere('execution_status', '!=', 'succeeded'))
            ->update(['approval_status' => 'invalidated', 'invalidated_at' => now(), 'invalidated_reason' => $reason]) > 0;
    }

    /** Absolute landing page URL sent to Meta. */
    public static function absoluteLanding(MetaCampaignDraft $d): ?string
    {
        return $d->landing_url ? rtrim((string) config('app.url'), '/').$d->landing_url : null;
    }

    // ------------------------------------------------------------------ presentation (API / MCP: never tokens, never secrets)

    public function present(MetaCampaignDraft $d, bool $detail = false, ?int $tokenId = null): array
    {
        $approval = $d->validApproval();
        $out = ['id' => $d->id, 'name' => $d->name, 'objective' => $d->objective, 'status' => $d->status->value, 'version' => $d->version, 'content_hash' => $d->content_hash,
            'budget_type' => $d->budget_type, 'budget_amount' => (string) $d->budget_amount, 'currency' => $d->currency,
            'start_at' => $d->start_at?->toIso8601String(), 'end_at' => $d->end_at?->toIso8601String(), 'product_ids' => $d->product_ids ?? [],
            'own' => $tokenId !== null && (int) $d->created_by_token_id === $tokenId, 'submitted_at' => $d->submitted_at?->toIso8601String(),
            'approval' => $approval ? ['status' => 'approved', 'version' => $approval->draft_version, 'expires_at' => $approval->expires_at?->toIso8601String(),
                'execution_status' => $approval->execution_status] : null,
            'meta' => ['ad_account_id' => $d->ad_account_id ? 'act_'.$d->ad_account_id : null, 'campaign_id' => $d->meta_campaign_id, 'adset_id' => $d->meta_adset_id,
                'creative_id' => $d->meta_creative_id, 'ad_id' => $d->meta_ad_id, 'statuses' => $d->meta_statuses, 'published_at' => $d->published_at?->toIso8601String()],
            'last_error' => $d->last_error, 'created_at' => $d->created_at?->toIso8601String(), 'updated_at' => $d->updated_at?->toIso8601String(),
            'review_url' => route('marketer.meta-ads.show', $d->id)];
        if ($detail) {
            $out += ['product_urls' => $d->product_urls ?? [], 'audience' => $d->audience, 'locations' => $d->locations, 'placements' => $d->placements,
                'creative' => $d->creative, 'copy' => $d->copy, 'cta' => $d->cta, 'landing_url' => self::absoluteLanding($d), 'optimization_goal' => $d->optimization_goal,
                'expected_kpis' => (object) ($d->expected_kpis ?? []), 'risks' => $d->risks, 'assumptions' => $d->assumptions,
                'checks' => $this->check(self::columnsOf($d), $d, $d->status === S::PENDING_APPROVAL),
                'approvals' => $d->approvals()->get()->map(fn ($a) => ['id' => $a->id, 'version' => $a->draft_version, 'status' => $a->approval_status,
                    'approved_at' => $a->approved_at?->toIso8601String(), 'expires_at' => $a->expires_at?->toIso8601String(), 'execution_status' => $a->execution_status,
                    'invalidated_reason' => $a->invalidated_reason])->all(),
                'safety' => ['created_status' => 'PAUSED', 'activation_supported' => false, 'publish_requires' => 'panel approval + meta_ads.publish']];
        }

        return $out;
    }
}
