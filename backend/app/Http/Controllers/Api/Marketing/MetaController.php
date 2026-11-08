<?php

namespace App\Http\Controllers\Api\Marketing;

use App\DTOs\Marketing\MetaCampaignDraftData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Api\MetaDraftWriteRequest;
use App\Http\Requests\Marketing\Api\MetaReadRequest;
use App\Models\MarketingApiToken;
use App\Models\MetaCampaignDraft;
use App\Services\Marketing\MarketingActionException;
use App\Services\Marketing\Meta\MetaAudit;
use App\Services\Marketing\Meta\MetaCampaignDraftService;
use App\Services\Marketing\Meta\MetaException;
use App\Services\Marketing\Meta\MetaReadService;
use App\Services\Marketing\Suggestions\SuggestionActor;
use App\Support\Marketing\MarketingResponse as R;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Meta for the MCP server (P6, MCP.md §7). Read (scope meta:read): status, ad accounts, account, campaigns, ad sets, ads, insights (cached),
 * drafts list / detail. Write (scope meta:draft): create draft, edit own draft, submit own draft for approval.
 * There is deliberately NO approve / reject / publish / activate endpoint: approval and "publish as PAUSED" are marketer-panel actions only.
 * Tokens / secrets are never returned.
 */
class MetaController extends Controller
{
    public function __construct(private MetaReadService $reads, private MetaCampaignDraftService $drafts, private MetaAudit $audit) {}

    private function token(Request $r): MarketingApiToken
    {
        return $r->attributes->get('marketing_token');
    }

    private function read(Request $r, callable $fn): JsonResponse
    {
        $this->audit->actingAs(SuggestionActor::token($this->token($r)));
        try {
            return $fn();
        } catch (MetaException $e) {
            return R::error($e->errorCode === 'not_configured' ? 503 : $e->status, $e->errorCode, $e->getMessage(), null, $e->data ?: null);
        }
    }

    public function status(): JsonResponse
    {
        return R::ok($this->reads->status() + ['safety' => ['created_status' => 'PAUSED', 'activation_supported' => false, 'budget_changes_supported' => false, 'delete_supported' => false]]);
    }

    public function adAccounts(MetaReadRequest $r): JsonResponse
    {
        return $this->read($r, fn () => R::ok($this->reads->adAccounts()));
    }

    public function account(MetaReadRequest $r): JsonResponse
    {
        return $this->read($r, fn () => R::ok($this->reads->account()));
    }

    public function campaigns(MetaReadRequest $r): JsonResponse
    {
        return $this->read($r, fn () => $this->cursor($this->reads->campaigns((int) $r->input('limit', 25), $r->input('after'))));
    }

    public function adSets(MetaReadRequest $r): JsonResponse
    {
        return $this->read($r, fn () => $this->cursor($this->reads->adSets($r->input('campaign_id'), (int) $r->input('limit', 25), $r->input('after'))));
    }

    public function ads(MetaReadRequest $r): JsonResponse
    {
        return $this->read($r, fn () => $this->cursor($this->reads->ads($r->input('adset_id'), (int) $r->input('limit', 25), $r->input('after'))));
    }

    public function insights(MetaReadRequest $r): JsonResponse
    {
        return $this->read($r, function () use ($r) {
            $q = $r->filled('from') ? $r->toQuery() : null;
            $rows = $this->reads->insights($r->input('object_id'), (string) $r->input('level', 'account'), $r->input('date_preset', 'last_30d'), $q?->from, $q?->to);

            return R::ok($rows, $rows ? [] : [R::warning('missing_data', 'Meta returned no insights for this range.')], ['level' => $r->input('level', 'account'),
                'date_preset' => $q ? null : $r->input('date_preset', 'last_30d'), 'from' => $q?->from, 'to' => $q?->to]);
        });
    }

    private function cursor(array $page): JsonResponse
    {
        return R::ok($page['items'], [], ['next_cursor' => $page['next_cursor']]);
    }

    // ------------------------------------------------------------------ drafts

    public function drafts(MetaReadRequest $r): JsonResponse
    {
        $f = $r->filtersArray();
        $q = $r->toQuery();
        $tokenId = $this->token($r)->id;
        $page = $this->drafts->repo()->filtered(['status' => $f['status'] ?? null, 'search' => $f['search'] ?? null, 'token_id' => ! empty($f['mine']) ? $tokenId : null])
            ->paginate($q->perPage, ['*'], 'page', $q->page);

        return R::page($page, fn ($d) => $this->drafts->present($d, false, $tokenId));
    }

    public function draft(Request $r, int $id): JsonResponse
    {
        $d = MetaCampaignDraft::query()->find($id);

        return $d ? R::ok($this->drafts->present($d, true, $this->token($r)->id)) : R::error(404, 'not_found');
    }

    public function storeDraft(MetaDraftWriteRequest $r): JsonResponse
    {
        return $this->write(function () use ($r) {
            [$d, $warnings] = $this->drafts->create(MetaCampaignDraftData::fromArray($r->draftInput()), SuggestionActor::token($this->token($r)), $r->boolean('submit'));

            return R::ok($this->drafts->present($d->refresh(), true, $this->token($r)->id), array_map(fn ($w) => R::warning('check', $w), $warnings), [], 201);
        });
    }

    public function updateDraft(MetaDraftWriteRequest $r, int $id): JsonResponse
    {
        $d = MetaCampaignDraft::query()->find($id);
        if (! $d) {
            return R::error(404, 'not_found');
        }

        return $this->write(function () use ($r, $d) {
            [$d, $warnings] = $this->drafts->update($d, $r->draftInput(), SuggestionActor::token($this->token($r)));

            return R::ok($this->drafts->present($d->refresh(), true, $this->token($r)->id), array_map(fn ($w) => R::warning('check', $w), $warnings));
        });
    }

    public function submitDraft(Request $r, int $id): JsonResponse
    {
        $d = MetaCampaignDraft::query()->find($id);
        if (! $d) {
            return R::error(404, 'not_found');
        }

        return $this->write(fn () => R::ok($this->drafts->present($this->drafts->submit($d, SuggestionActor::token($this->token($r)))->refresh(), false, $this->token($r)->id)));
    }

    private function write(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (MarketingActionException $e) {
            return R::error($e->status, $e->errorCode, $e->getMessage(), $e->errors ?: null, $e->data ?: null);
        }
    }
}
