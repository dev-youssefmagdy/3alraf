<?php

namespace App\Http\Controllers\Api\Marketing;

use App\DTOs\Marketing\SuggestionEditData;
use App\DTOs\Marketing\SuggestionInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Api\StoreSuggestionRequest;
use App\Http\Requests\Marketing\Api\SuggestionListRequest;
use App\Http\Requests\Marketing\Api\UpdateSuggestionRequest;
use App\Models\MarketingApiToken;
use App\Models\MarketingSuggestion;
use App\Repositories\Contracts\MarketingSuggestionRepositoryInterface;
use App\Services\Marketing\Suggestions\SuggestionActor;
use App\Services\Marketing\Suggestions\SuggestionEngine;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Support\Marketing\MarketingResponse as R;
use App\Support\Marketing\SuggestionPresenter as P;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/internal/marketing/suggestions (P5). Read (scope marketing:read): list, detail, status, result. Write (scope suggestions:write):
 * create per type, edit own pending/edited draft, submit for review. There is NO approve / reject / apply / rollback endpoint: those are
 * dashboard-only actions (MCP.md §22.3, §27).
 */
class SuggestionController extends Controller
{
    public function __construct(private SuggestionEngine $engine, private MarketingSuggestionRepositoryInterface $repo) {}

    private function token(Request $r): MarketingApiToken
    {
        return $r->attributes->get('marketing_token');
    }

    private function find(int $id): ?MarketingSuggestion
    {
        return $this->repo->find($id);
    }

    private static function fail(SuggestionException $e): JsonResponse
    {
        return R::error($e->status, $e->errorCode, $e->getMessage(), $e->errors ?: null, $e->data ?: null);
    }

    public function index(SuggestionListRequest $r): JsonResponse
    {
        $f = $r->filtersArray();
        $range = $r->sentRange();
        $q = $this->repo->filtered(['types' => isset($f['type']) ? [$f['type']] : [], 'status' => $f['status'] ?? null, 'target_entity_type' => $f['target_entity_type'] ?? null,
            'target_entity_id' => $f['target_entity_id'] ?? null, 'token_id' => ! empty($f['mine']) ? $this->token($r)->id : null, 'search' => $f['search'] ?? null,
            'from' => $range[0] ?? null, 'to' => $range[1] ?? null, 'sort' => $f['sort'] ?? 'newest']);
        $tq = $r->toQuery();
        $tokenId = $this->token($r)->id;

        return R::page($q->paginate($tq->perPage, ['*'], 'page', $tq->page), fn ($s) => ['own' => (int) $s->created_by_token_id === $tokenId] + P::summary($s));
    }

    public function show(Request $r, int $id): JsonResponse
    {
        $s = $this->find($id);

        return $s ? R::ok(['own' => (int) $s->created_by_token_id === $this->token($r)->id] + P::detail($s, $this->engine)) : R::error(404, 'not_found');
    }

    public function status(int $id): JsonResponse
    {
        return ($s = $this->find($id)) ? R::ok(P::status($s)) : R::error(404, 'not_found');
    }

    public function result(int $id): JsonResponse
    {
        return ($s = $this->find($id)) ? R::ok(P::result($s)) : R::error(404, 'not_found');
    }

    public function store(StoreSuggestionRequest $r, string $type): JsonResponse
    {
        $v = $r->validated();
        try {
            [$s, $warnings] = $this->engine->create(SuggestionInput::fromArray($type, $v, $v['source'] ?? 'mcp'), SuggestionActor::token($this->token($r)));
        } catch (SuggestionException $e) {
            return self::fail($e);
        }

        return R::ok(['own' => true] + P::detail($s->fresh(), $this->engine), array_map(fn ($w) => R::warning('suggestion', $w), $warnings), [], 201);
    }

    public function update(UpdateSuggestionRequest $r, int $id): JsonResponse
    {
        $s = $r->suggestion();
        if (! $s) {
            return R::error(404, 'not_found');
        }
        try {
            [$s, $warnings] = $this->engine->update($s, SuggestionEditData::fromArray($r->validated()), SuggestionActor::token($this->token($r)));
        } catch (SuggestionException $e) {
            return self::fail($e);
        }

        return R::ok(['own' => true] + P::detail($s->fresh(), $this->engine), array_map(fn ($w) => R::warning('suggestion', $w), $warnings));
    }

    public function submit(Request $r, int $id): JsonResponse
    {
        $s = $this->find($id);
        if (! $s) {
            return R::error(404, 'not_found');
        }
        try {
            $this->engine->submit($s, SuggestionActor::token($this->token($r)));
        } catch (SuggestionException $e) {
            return self::fail($e);
        }

        return R::ok(P::status($s->fresh()));
    }
}
