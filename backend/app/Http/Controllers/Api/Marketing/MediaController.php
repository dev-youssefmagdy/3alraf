<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\DTOs\Marketing\SocialMediaAssetData;
use App\Http\Requests\Marketing\Api\MediaListRequest;
use App\Services\Marketing\MarketingActionException;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\SocialMediaAsset;
use App\Services\Marketing\Media\SocialMediaLibraryService;
use App\Support\Marketing\MarketingResponse as R;
use Illuminate\Http\JsonResponse;

/**
 * Private product social-media library, read-only (P6, scope marketing:read): metadata only (never storage paths or file bytes) plus a
 * short-lived signed preview URL per file. Library items are created by approved social_content / media_asset suggestions, uploads happen
 * in the marketer panel — plus ONE opt-in write, POST media/upload (scope media:write): a locally generated image / video for a product, stored
 * privately as `pending_review` (a person approves it in the marketer panel). Idempotent by `client_ref`.
 */
class MediaController extends Controller
{
    public function __construct(private SocialMediaLibraryService $media) {}

    public function index(MediaListRequest $r): JsonResponse
    {
        $q = $r->toQuery();

        return R::page($this->media->repo()->filtered($r->filtersArray())->paginate($q->perPage, ['*'], 'page', $q->page), fn ($a) => $this->media->present($a, false, true),
            [R::warning('internal_only', 'Internal marketing assets: never public, never published automatically. Preview URLs expire.')]);
    }

    public function product(MediaListRequest $r, int $id): JsonResponse
    {
        if (! Product::withTrashed()->whereKey($id)->exists()) {
            return R::error(404, 'not_found');
        }
        $q = $r->toQuery();
        $page = $this->media->repo()->filtered(['product_id' => $id] + $r->filtersArray())->paginate($q->perPage, ['*'], 'page', $q->page);

        return R::page($page, fn ($a) => $this->media->present($a, false, true), $page->total() ? [] : [R::warning('missing_data', 'This product has no social-media library items.', ['field' => 'social_media'])]);
    }

    public function show(int $id): JsonResponse
    {
        $a = SocialMediaAsset::query()->with('product:id,name,sku')->find($id);

        return $a ? R::ok($this->media->present($a, true, true)) : R::error(404, 'not_found');
    }

    /** POST media/upload (scope media:write). multipart: file, product_id, client_ref (idempotency), title?, tags?, brief?, generation_meta? (JSON object). */
    public function upload(Request $r): JsonResponse
    {
        $v = validator($r->all(), [
            'file' => ['required', 'file', 'max:'.(int) max(config('marketing.media.image_max_kb'), config('marketing.media.video_max_kb'))],
            'product_id' => ['required', 'integer'],
            'client_ref' => ['required', 'string', 'min:6', 'max:80', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'title' => ['nullable', 'string', 'max:200'], 'tags' => ['nullable', 'string', 'max:300'], 'brief' => ['nullable', 'string', 'max:3000'],
            'generation_meta' => ['nullable', 'json', 'max:4000'],
        ]);
        if ($v->fails()) {
            return R::error(422, 'validation_failed', (string) $v->errors()->first(), $v->errors()->toArray());
        }
        $in = $v->validated();
        $product = Product::withTrashed()->find($in['product_id']);
        if (! $product || $product->trashed()) {
            return R::error(404, 'not_found');
        }
        // idempotent: the same client_ref never creates a second item (retries after a timeout are safe)
        $existing = SocialMediaAsset::query()->where('product_id', $product->id)->where('generation_meta->client_ref', $in['client_ref'])->first();
        if ($existing) {
            return R::ok($this->uploadResult($existing), [R::warning('duplicate', 'Already uploaded with this client_ref; nothing was created.')]);
        }
        $meta = is_string($in['generation_meta'] ?? null) ? (array) json_decode($in['generation_meta'], true) : [];
        $meta = array_intersect_key($meta, array_flip(['origin', 'generation_id', 'virtual_model', 'provider', 'seed', 'product_fidelity', 'engine', 'model']));
        $data = SocialMediaAssetData::fromArray(['kind' => null, 'title' => $in['title'] ?? null, 'tags' => $in['tags'] ?? 'ai-casting', 'brief' => $in['brief'] ?? null,
            'generation_meta' => ['client_ref' => $in['client_ref'], 'origin' => 'ai_casting_local'] + $meta]);
        try {
            $asset = $this->media->create($product, $data, $r->file('file'), null);
        } catch (MarketingActionException $e) {
            return R::error($e->status, $e->errorCode, $e->getMessage(), $e->errors ?: null);
        }

        return R::ok($this->uploadResult($asset), [R::warning('internal_only', 'Stored privately as pending_review. A person must approve it in the marketer panel; nothing is published.')], [], 201);
    }

    private function uploadResult(SocialMediaAsset $a): array
    {
        return ['id' => $a->id, 'product_id' => $a->product_id, 'kind' => $a->kind->value, 'status' => $a->status->value, 'version' => $a->version,
            'review_url' => rtrim((string) config('app.url'), '/').'/marketer/media/'.$a->id];
    }
}
