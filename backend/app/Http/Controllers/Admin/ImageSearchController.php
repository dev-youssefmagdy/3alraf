<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\ImageSearchQueryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImageSearchRequest;
use App\Services\ImageSearch\EmbedderClient;
use App\Services\ImageSearch\EmbedderUnavailableException;
use App\Services\ImageSearch\ImageEmbeddingService;
use App\Services\ImageSearch\ImageSearchService;
use App\Services\ImageSearch\InvalidImageException;
use Illuminate\Http\JsonResponse;

/**
 * Admin → Products → search by image (IMAGE_SEARCH_PLAN.md). Routes need products.view and the `image-search` rate limit.
 * The uploaded photo is only read from PHP's temporary upload file and never stored.
 */
class ImageSearchController extends Controller
{
    public function search(ImageSearchRequest $request, ImageSearchService $search): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $search->search(ImageSearchQueryData::fromRequest($request))]);
        } catch (EmbedderUnavailableException $e) {
            report($e);

            return response()->json(['success' => false, 'error' => 'unavailable', 'message' => __('admin.image_search.down_title')], 503);
        } catch (InvalidImageException) {
            $ext = strtoupper((string) $request->file('image')?->getClientOriginalExtension() ?: '?');

            return response()->json(['success' => false, 'error' => 'invalid_image', 'message' => __('admin.image_search.wrong_type', ['ext' => $ext]),
                'errors' => ['image' => [__('admin.image_search.wrong_type', ['ext' => $ext])]]], 422);
        }
    }

    /** Coverage strip of the modal: indexed / total images and whether the embedder answers. */
    public function coverage(ImageEmbeddingService $embeddings, EmbedderClient $client): JsonResponse
    {
        $s = $embeddings->status();

        return response()->json(['success' => true, 'data' => ['total' => $s['total'], 'indexed' => $s['indexed'], 'waiting' => $s['waiting'],
            'percent' => $s['percent'], 'complete' => $s['complete'], 'service_up' => $client->health()['up']]]);
    }
}
