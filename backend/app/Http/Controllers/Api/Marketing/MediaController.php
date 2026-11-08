<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Api\MediaListRequest;
use App\Models\Product;
use App\Models\SocialMediaAsset;
use App\Services\Marketing\Media\SocialMediaLibraryService;
use App\Support\Marketing\MarketingResponse as R;
use Illuminate\Http\JsonResponse;

/**
 * Private product social-media library, read-only (P6, scope marketing:read): metadata only (never storage paths or file bytes) plus a
 * short-lived signed preview URL per file. Library items are created by approved social_content / media_asset suggestions, uploads happen
 * in the marketer panel.
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
}
