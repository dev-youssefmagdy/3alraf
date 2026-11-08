<?php

namespace App\Services\Marketing\Meta;

use App\Enums\Marketing\MediaAssetStatus;
use App\Enums\Marketing\MetaDraftStatus as S;
use App\Models\Admin;
use App\Models\MetaCampaignApproval;
use App\Models\MetaCampaignDraft;
use App\Models\SocialMediaAsset;
use App\Repositories\Contracts\MetaCampaignDraftRepositoryInterface;
use App\Services\Marketing\MarketingActionException as E;
use App\Services\Marketing\Media\SocialMediaLibraryService;
use App\Services\Marketing\Suggestions\SuggestionActor;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * "Publish as PAUSED" (P6, MCP.md §7 steps 7–11). Panel only, permission meta_ads.publish (owner by default).
 * Checks independently of any caller: valid approval for the CURRENT version, content hash recomputed from the stored row equals both the
 * draft hash and the approval hash, approval not expired, Meta configured. Then creates — each step skipped when its id is already stored
 * (a retry after a failure resumes, never duplicates) — campaign → ad set → (image upload) → creative → ad, ALL with status PAUSED, reads
 * the statuses back and requires PAUSED for campaign / ad set / ad, stores the ids + verified statuses, and audits every step.
 * Idempotency: one key per approval (default publish:{draft}:v{version}:{hash16}); a succeeded key returns the stored result without any call.
 * There is no activate / budget-change / delete path anywhere (MetaGraphClient only reads and creates).
 */
class MetaPublisher
{
    public const STALE_MINUTES = 15;

    public function __construct(private MetaGraphClient $client, private MetaCampaignDraftRepositoryInterface $repo, private MetaAudit $audit,
        private SocialMediaLibraryService $media) {}

    public function publish(MetaCampaignDraft $d, Admin $admin, ?string $key = null): MetaCampaignApproval
    {
        $actor = SuggestionActor::admin($admin);
        $this->audit->actingAs($actor);
        if (! $admin->can('meta_ads.publish')) {
            throw E::make('meta', 'forbidden', 403, ['permission' => 'meta_ads.publish']);
        }
        if (! $this->client->configured()) {
            throw E::make('meta', 'not_configured', 503);
        }
        if (! $this->client->pageConfigured()) {
            throw E::make('meta', 'page_missing', 503);
        }

        $this->releaseInterrupted($d, $actor);

        try {
            [$d, $approval, $done] = DB::transaction(function () use ($d, $key, $actor) {
                $d = $this->repo->lockForUpdate($d->id);
                $approval = $d->validApproval();
                if ($approval && $approval->execution_status === 'succeeded') {
                    return [$d, $approval, true];   // idempotent replay
                }
                if ($d->status === S::PUBLISHED) {
                    return [$d, $approval ?? $d->approvals()->where('execution_status', 'succeeded')->firstOrFail(), true];
                }
                if ($d->status === S::PUBLISHING || ($approval && $approval->execution_status === 'running')) {
                    throw E::make('meta', 'in_progress', 409);
                }
                if (! in_array($d->status, [S::APPROVED, S::FAILED], true) || ! $approval) {
                    throw E::make('meta', 'approval_required', 409, ['status' => $d->status->value]);
                }
                $hash = MetaCampaignDraftService::hash($d);
                if ($hash !== $d->content_hash || $hash !== $approval->content_hash || $approval->draft_version !== $d->version) {
                    $this->audit->log($d->id, 'hash_mismatch', $actor, ['approval_id' => $approval->id]);
                    throw E::make('meta', 'hash_mismatch', 409);
                }
                $k = $approval->idempotency_key ?: mb_substr($key ? 'k:'.$key : 'publish:'.$d->id.':v'.$d->version.':'.substr($d->content_hash, 0, 16), 0, 100);
                $approval->update(['idempotency_key' => $k, 'execution_status' => 'running', 'execution_error' => null]);
                $this->repo->update($d, ['status' => S::PUBLISHING, 'last_error' => null, 'ad_account_id' => $this->client->accountDigits()]);
                $this->audit->log($d->id, 'publish_started', $actor, ['approval_id' => $approval->id, 'idempotency_key' => $k, 'version' => $d->version]);

                return [$d, $approval, false];
            });
        } catch (QueryException $e) {
            throw E::make('meta', 'in_progress', 409);   // the same idempotency key is already used by another approval row
        }
        if ($done) {
            return $approval;
        }

        try {
            $ids = $this->createObjects($d);
            $statuses = $this->verify($d, $ids);
        } catch (MetaException|E $e) {
            $msg = mb_substr($e->getMessage(), 0, 2000);
            DB::transaction(function () use ($d, $approval, $msg, $actor, $e) {
                $approval->update(['execution_status' => 'failed', 'execution_error' => $msg, 'external_reference' => $this->ids($d->refresh())]);
                $this->repo->update($d, ['status' => S::FAILED, 'last_error' => $msg]);
                $this->audit->log($d->id, 'publish_failed', $actor, ['approval_id' => $approval->id, 'error' => $e->errorCode,
                    'message' => mb_substr($msg, 0, 500), 'ids' => $this->ids($d)]);
            });

            return $approval->refresh();
        }
        DB::transaction(function () use ($d, $approval, $ids, $statuses, $admin, $actor) {
            $approval->update(['execution_status' => 'succeeded', 'external_reference' => $ids + ['statuses' => $statuses]]);
            $this->repo->update($d, ['status' => S::PUBLISHED, 'meta_statuses' => $statuses, 'published_by' => $admin->id, 'published_at' => now(), 'last_error' => null]);
            $this->audit->log($d->id, 'published_paused', $actor, ['approval_id' => $approval->id, 'ids' => $ids, 'statuses' => $statuses]);
        });

        return $approval->refresh();
    }

    /** A run that died mid-way (process killed, timeout) stays `publishing`: after STALE_MINUTES without progress it becomes `failed`, so it can be resumed or edited. */
    private function releaseInterrupted(MetaCampaignDraft $d, SuggestionActor $actor): void
    {
        if (! self::interrupted($d)) {
            return;
        }
        $msg = __('marketing.meta.errors.interrupted');
        DB::transaction(function () use ($d, $actor, $msg) {
            MetaCampaignApproval::query()->where('draft_id', $d->id)->where('execution_status', 'running')->update(['execution_status' => 'failed', 'execution_error' => $msg]);
            $this->repo->update($d, ['status' => S::FAILED, 'last_error' => $msg]);
            $this->audit->log($d->id, 'publish_interrupted', $actor, ['ids' => $this->ids($d)]);
        });
    }

    public static function interrupted(MetaCampaignDraft $d): bool
    {
        return $d->status === S::PUBLISHING && $d->updated_at && $d->updated_at->lt(now()->subMinutes(self::STALE_MINUTES));
    }

    // ------------------------------------------------------------------ steps

    private function createObjects(MetaCampaignDraft $d): array
    {
        if (! $d->meta_campaign_id) {
            $r = $this->client->create('campaigns', $this->campaignPayload($d), $d->id);
            $this->store($d, 'meta_campaign_id', $r);
        }
        if (! $d->meta_adset_id) {
            $r = $this->client->create('adsets', $this->adSetPayload($d), $d->id);
            $this->store($d, 'meta_adset_id', $r);
        }
        if (! $d->meta_image_hash && ($asset = $this->imageAsset($d))) {
            $bytes = SocialMediaLibraryService::disk()->get($asset->path);
            if ($bytes) {
                $r = $this->client->create('adimages', ['bytes' => base64_encode($bytes)], $d->id);
                $hash = collect((array) ($r['images'] ?? []))->first()['hash'] ?? null;
                if ($hash) {
                    $this->repo->update($d, ['meta_image_hash' => mb_substr((string) $hash, 0, 64)]);
                }
            }
        }
        if (! $d->meta_creative_id) {
            $r = $this->client->create('adcreatives', $this->creativePayload($d), $d->id);
            $this->store($d, 'meta_creative_id', $r);
        }
        if (! $d->meta_ad_id) {
            $r = $this->client->create('ads', $this->adPayload($d), $d->id);
            $this->store($d, 'meta_ad_id', $r);
        }

        return $this->ids($d);
    }

    /** Read back campaign / ad set / ad statuses; anything but PAUSED fails the execution (nothing is changed in Meta — the owner checks Ads Manager). */
    private function verify(MetaCampaignDraft $d, array $ids): array
    {
        $out = [];
        foreach (['campaign' => $ids['campaign_id'], 'adset' => $ids['adset_id'], 'ad' => $ids['ad_id']] as $k => $id) {
            $r = $this->client->get((string) $id, ['fields' => 'id,status,effective_status'], $d->id);
            $out[$k] = ['status' => $r['status'] ?? null, 'effective_status' => $r['effective_status'] ?? null];
        }
        $bad = array_keys(array_filter($out, fn ($s) => $s['status'] !== 'PAUSED'));
        if ($bad) {
            $this->repo->update($d, ['meta_statuses' => $out]);
            $this->audit->log($d->id, 'status_not_paused', null, ['objects' => $bad, 'statuses' => $out]);
            throw E::make('meta', 'status_not_paused', 409, ['objects' => $bad]);
        }

        return $out;
    }

    private function store(MetaCampaignDraft $d, string $col, array $r): void
    {
        $id = (string) ($r['id'] ?? '');
        if (! preg_match('/^\d{1,40}$/', $id)) {
            throw new MetaException('meta_error', 'Meta returned no object id.', 502);
        }
        $this->repo->update($d, [$col => $id]);   // stored right away: a retry resumes after this step
    }

    private function ids(MetaCampaignDraft $d): array
    {
        return ['campaign_id' => $d->meta_campaign_id, 'adset_id' => $d->meta_adset_id, 'creative_id' => $d->meta_creative_id, 'ad_id' => $d->meta_ad_id,
            'image_hash' => $d->meta_image_hash, 'ad_account_id' => $d->ad_account_id ? 'act_'.$d->ad_account_id : null];
    }

    private function imageAsset(MetaCampaignDraft $d): ?SocialMediaAsset
    {
        $ids = (array) ($d->creative['asset_ids'] ?? []);

        return $ids ? SocialMediaAsset::query()->whereIn('id', $ids)->where('status', MediaAssetStatus::APPROVED)->whereNotNull('path')->where('mime', 'like', 'image/%')
            ->orderByRaw('FIELD(id, '.implode(',', array_map('intval', $ids)).')')->first() : null;
    }

    // ------------------------------------------------------------------ payloads (public for tests / docs: every one is PAUSED)

    public function campaignPayload(MetaCampaignDraft $d): array
    {
        return ['name' => $d->name, 'objective' => $d->objective, 'status' => 'PAUSED', 'special_ad_categories' => [], 'buying_type' => 'AUCTION',
            'is_adset_budget_sharing_enabled' => 'false'];
    }

    public function adSetPayload(MetaCampaignDraft $d): array
    {
        $minor = (int) round((float) $d->budget_amount * 100);
        $aud = (array) $d->audience;
        $pl = (array) $d->placements;
        $targeting = array_filter([
            'geo_locations' => ['countries' => array_values((array) ($d->locations['countries'] ?? ['EG']))],
            'age_min' => (int) ($aud['age_min'] ?? 18), 'age_max' => (int) ($aud['age_max'] ?? 65),
            'genders' => match ($aud['genders'] ?? 'all') { 'male' => [1], 'female' => [2], default => null },
            'targeting_automation' => ['advantage_audience' => 0],
        ], fn ($v) => $v !== null);
        if (! ($pl['automatic'] ?? true)) {
            $targeting['publisher_platforms'] = array_values((array) ($pl['publisher_platforms'] ?? []));
            if ($pl['facebook_positions'] ?? []) {
                $targeting['facebook_positions'] = array_values($pl['facebook_positions']);
            }
            if ($pl['instagram_positions'] ?? []) {
                $targeting['instagram_positions'] = array_values($pl['instagram_positions']);
            }
        }

        return array_filter([
            'name' => mb_substr($d->name.' — ad set', 0, 200), 'campaign_id' => $d->meta_campaign_id, 'status' => 'PAUSED',
            $d->budget_type === 'lifetime' ? 'lifetime_budget' : 'daily_budget' => $minor,
            'billing_event' => 'IMPRESSIONS', 'optimization_goal' => $d->optimization_goal ?: 'LINK_CLICKS', 'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
            'targeting' => $targeting,
            'start_time' => $d->start_at ? CarbonImmutable::parse($d->start_at)->toIso8601String() : null,
            'end_time' => $d->end_at ? CarbonImmutable::parse($d->end_at)->toIso8601String() : null,
        ], fn ($v) => $v !== null);
    }

    public function creativePayload(MetaCampaignDraft $d): array
    {
        $link = MetaCampaignDraftService::absoluteLanding($d);
        $copy = (array) $d->copy;
        $linkData = array_filter(['link' => $link, 'message' => $copy['primary_text'] ?? null, 'name' => $copy['headline'] ?? null, 'description' => $copy['description'] ?? null,
            'image_hash' => $d->meta_image_hash, 'call_to_action' => ['type' => $d->cta ?: 'SHOP_NOW', 'value' => ['link' => $link]]], fn ($v) => $v !== null && $v !== '');

        return ['name' => mb_substr($d->name.' — creative', 0, 200), 'object_story_spec' => array_filter(['page_id' => (string) config('meta.page_id'),
            'instagram_user_id' => (string) config('meta.instagram_user_id') ?: null, 'link_data' => $linkData])];
    }

    public function adPayload(MetaCampaignDraft $d): array
    {
        return ['name' => mb_substr($d->name.' — ad', 0, 200), 'adset_id' => $d->meta_adset_id, 'creative' => ['creative_id' => $d->meta_creative_id], 'status' => 'PAUSED'];
    }
}
