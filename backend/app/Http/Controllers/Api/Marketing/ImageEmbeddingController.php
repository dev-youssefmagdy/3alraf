<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Api\FillMissingEmbeddingsRequest;
use App\Services\ImageSearch\EmbedderClient;
use App\Services\ImageSearch\ImageEmbeddingService;
use App\Support\Marketing\MarketingResponse as R;
use Illuminate\Http\JsonResponse;

/**
 * Image-search index for the MCP server (IMAGE_SEARCH_PLAN.md §3.4, MCP.md "image embeddings").
 * GET status (marketing:read) and POST fill-missing (embeddings:write): queues ONLY images / bundles without an embedding for the current
 * model (or whose file changed). Never recomputes existing embeddings and changes no store content: it writes the internal index only.
 */
class ImageEmbeddingController extends Controller
{
    public function __construct(private ImageEmbeddingService $embeddings, private EmbedderClient $client) {}

    public function status(): JsonResponse
    {
        return R::ok($this->present($this->embeddings->status()));
    }

    public function fillMissing(FillMissingEmbeddingsRequest $r): JsonResponse
    {
        $res = $this->embeddings->fillMissing($r->validated('limit'));

        return R::ok(['queued' => $res['queued'], 'remaining' => $res['remaining'], 'pruned_orphans' => $res['pruned'], 'status' => $this->present($res['status'])],
            $this->client->health()['up'] ? [] : [R::warning('embedder_unreachable', 'The embedder service is not answering: queued jobs wait and retry; nothing is lost.')]);
    }

    private function present(array $s): array
    {
        return ['model' => $s['model'], 'total' => $s['total'], 'indexed' => $s['indexed'], 'pending' => $s['pending'], 'failed' => $s['failed'],
            'missing' => $s['missing'], 'percent' => $s['percent'], 'complete' => $s['complete'],
            'images' => $s['by_type']['image'] ?? ['total' => 0, 'indexed' => 0], 'bundles' => $s['by_type']['bundle'] ?? ['total' => 0, 'indexed' => 0],
            'embedder_reachable' => $this->client->health()['up']];
    }
}
