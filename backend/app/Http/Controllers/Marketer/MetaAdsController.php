<?php

namespace App\Http\Controllers\Marketer;

use App\DTOs\Marketing\MetaCampaignDraftData;
use App\Enums\Marketing\MediaAssetStatus;
use App\Enums\Marketing\MetaDraftStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketer\MediaDecisionRequest;
use App\Http\Requests\Marketer\MetaDraftRequest;
use App\Models\MetaCampaignDraft;
use App\Models\Product;
use App\Models\SocialMediaAsset;
use App\Services\Marketing\MarketingActionException;
use App\Services\Marketing\Meta\MetaAudit;
use App\Services\Marketing\Meta\MetaCampaignDraftService;
use App\Services\Marketing\Meta\MetaException;
use App\Services\Marketing\Meta\MetaPublisher;
use App\Services\Marketing\Meta\MetaReadService;
use App\Services\Marketing\Suggestions\SuggestionActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Meta campaign drafts + approval + "publish as PAUSED" (P6, MCP.md §7/§8), /marketer/meta-ads (names marketer.meta-ads.*).
 * view: meta_ads.view (drafts, account read panels, insights); create / edit / submit: meta_ads.draft; approve / reject: meta_ads.approve;
 * publish (always PAUSED): meta_ads.publish (owner only by default); archive: meta_ads.draft.
 */
class MetaAdsController extends Controller
{
    public function __construct(private MetaCampaignDraftService $drafts, private MetaPublisher $publisher, private MetaReadService $reads, private MetaAudit $audit) {}

    public function index(Request $r)
    {
        $status = in_array($r->query('status'), MetaDraftStatus::values(), true) ? $r->query('status') : null;
        $drafts = $this->drafts->repo()->filtered(['status' => $status, 'search' => is_string($r->query('q')) ? $r->query('q') : null])->paginate(20)->withQueryString();
        $meta = ['status' => $this->reads->status(), 'account' => null, 'campaigns' => null, 'insights' => null, 'error' => null];
        if ($meta['status']['configured'] && $r->boolean('live', true)) {
            $this->audit->actingAs(SuggestionActor::admin($r->user('admin')));
            try {
                $meta['account'] = $this->reads->account();
                $meta['campaigns'] = $this->reads->campaigns(10)['items'];
                $meta['insights'] = $this->reads->insights(null, 'account', 'last_30d')[0] ?? null;
            } catch (MetaException $e) {
                $meta['error'] = $e->getMessage();
            }
        }

        return view('marketer.meta-ads.index', ['drafts' => $drafts, 'status' => $status, 'counts' => $this->drafts->repo()->counts(), 'meta' => $meta]);
    }

    public function create(Request $r)
    {
        $d = new MetaCampaignDraft(['objective' => 'OUTCOME_SALES', 'budget_type' => 'daily', 'cta' => 'SHOP_NOW', 'currency' => config('meta.default_currency'),
            'audience' => ['age_min' => 18, 'age_max' => 65, 'genders' => 'all'], 'locations' => ['countries' => [strtoupper((string) (setting('country') ?: 'EG'))]],
            'placements' => ['automatic' => true], 'product_ids' => array_filter([$r->integer('product_id') ?: null]),
            'start_at' => now()->addDay()->startOfDay()]);

        return view('marketer.meta-ads.form', $this->formData($d));
    }

    public function edit(int $id)
    {
        $d = $this->find($id);
        abort_if($d->hasMetaObjects() || ! $d->status->editable(), 404);

        return view('marketer.meta-ads.form', $this->formData($d));
    }

    private function formData(MetaCampaignDraft $d): array
    {
        return ['d' => $d, 'products' => Product::query()->orderByDesc('id')->limit(2000)->get(['id', 'name', 'sku']),
            'assets' => SocialMediaAsset::query()->whereIn('kind', ['image', 'video'])->whereIn('status', [MediaAssetStatus::APPROVED, MediaAssetStatus::PENDING_REVIEW])
                ->with('product:id,name')->orderByDesc('id')->limit(300)->get(['id', 'product_id', 'title', 'kind', 'status'])];
    }

    public function show(int $id)
    {
        $d = $this->find($id);
        $check = $this->drafts->check(MetaCampaignDraftService::columnsOf($d), $d, $d->status === MetaDraftStatus::PENDING_APPROVAL);
        $assets = SocialMediaAsset::query()->whereIn('id', (array) ($d->creative['asset_ids'] ?? []))->get();

        return view('marketer.meta-ads.show', ['d' => $d, 'check' => $check, 'approval' => $d->validApproval(), 'approvals' => $d->approvals()->with('approver:id,name')->get(),
            'logs' => $d->auditLogs()->limit(200)->get(), 'assets' => $assets, 'products' => Product::query()->whereIn('id', (array) $d->product_ids)->get(['id', 'name', 'sku', 'price', 'is_active']),
            'hashOk' => MetaCampaignDraftService::hash($d) === $d->content_hash, 'status' => $this->reads->status(),
            'payloads' => ['campaign' => $this->publisher->campaignPayload($d), 'adset' => $this->publisher->adSetPayload($d), 'ad' => $this->publisher->adPayload($d)]]);
    }

    public function validateForm(MetaDraftRequest $r): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function store(MetaDraftRequest $r): JsonResponse
    {
        return $this->run(function () use ($r) {
            [$d] = $this->drafts->create(MetaCampaignDraftData::fromArray($r->draftInput()), SuggestionActor::admin($r->user('admin')), $r->boolean('submit'));

            return $this->ok(__('marketing.meta.done.created'), route('marketer.meta-ads.show', $d->id));
        });
    }

    public function update(MetaDraftRequest $r, int $id): JsonResponse
    {
        return $this->run(function () use ($r, $id) {
            $actor = SuggestionActor::admin($r->user('admin'));
            [$d] = $this->drafts->update($this->find($id), $r->draftInput(), $actor);
            if ($r->boolean('submit')) {
                $this->drafts->submit($d, $actor);
            }

            return $this->ok(__('marketing.meta.done.saved', ['v' => $d->version]), route('marketer.meta-ads.show', $d->id));
        });
    }

    public function submit(Request $r, int $id): JsonResponse
    {
        return $this->run(fn () => $this->drafts->submit($this->find($id), SuggestionActor::admin($r->user('admin'))) ? $this->ok(__('marketing.meta.done.submitted')) : null);
    }

    public function approve(MediaDecisionRequest $r, int $id): JsonResponse
    {
        return $this->run(fn () => $this->drafts->approve($this->find($id), $r->user('admin'), $r->input('note')) ? $this->ok(__('marketing.meta.done.approved')) : null);
    }

    public function reject(MediaDecisionRequest $r, int $id): JsonResponse
    {
        return $this->run(fn () => $this->drafts->reject($this->find($id), $r->user('admin'), $r->input('note')) ? $this->ok(__('marketing.meta.done.rejected'), route('marketer.meta-ads.show', $id)) : null);
    }

    public function archive(Request $r, int $id): JsonResponse
    {
        return $this->run(fn () => $this->drafts->archive($this->find($id), $r->user('admin')) ? $this->ok(__('marketing.meta.done.archived')) : null);
    }

    public function publish(MediaDecisionRequest $r, int $id): JsonResponse
    {
        return $this->run(function () use ($r, $id) {
            $a = $this->publisher->publish($this->find($id), $r->user('admin'), $r->input('idempotency_key'));
            if ($a->execution_status !== 'succeeded') {
                return response()->json(['success' => false, 'error' => 'publish_failed', 'message' => __('marketing.meta.errors.publish_failed', ['error' => (string) $a->execution_error])], 422);
            }

            return $this->ok(__('marketing.meta.done.published'));
        });
    }

    public function insights(Request $r)
    {
        $preset = in_array($r->query('preset'), (array) config('meta.insights_presets'), true) ? $r->query('preset') : 'last_30d';
        $level = in_array($r->query('level'), ['campaign', 'adset', 'ad'], true) ? $r->query('level') : 'campaign';
        $rows = null;
        $error = null;
        $status = $this->reads->status();
        if ($status['configured']) {
            $this->audit->actingAs(SuggestionActor::admin($r->user('admin')));
            try {
                $rows = $this->reads->insights(null, $level, $preset);
            } catch (MetaException $e) {
                $error = $e->getMessage();
            }
        }

        return view('marketer.meta-ads.insights', compact('rows', 'error', 'status', 'preset', 'level'));
    }

    private function find(int $id): MetaCampaignDraft
    {
        return MetaCampaignDraft::query()->findOrFail($id);
    }

    private function run(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (MarketingActionException $e) {
            return response()->json(['success' => false, 'error' => $e->errorCode, 'message' => $e->getMessage(), 'errors' => $e->errors ?: null, 'data' => $e->data ?: null], $e->status);
        } catch (MetaException $e) {
            return response()->json(['success' => false, 'error' => $e->errorCode, 'message' => $e->getMessage()], $e->status);
        }
    }
}
