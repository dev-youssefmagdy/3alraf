<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketer\MediaDecisionRequest;
use App\Http\Requests\Marketer\SocialMediaAssetRequest;
use App\Models\Product;
use App\Models\SocialMediaAsset;
use App\Services\Marketing\MarketingActionException;
use App\Services\Marketing\Media\SocialMediaLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Private product social-media library (P6, MCP.md §21). Routes /marketer/media/* (names marketer.media.*), each with its own permission:
 * view / preview / download ai_media.view, upload ai_media.upload, edit / submit ai_media.edit, approve / reject ai_media.approve,
 * delete ai_media.delete. The admin product edit page embeds the same library (tab "محتوى السوشيال ميديا") and posts to these routes.
 * Files are streamed by file()/thumb()/download() only — there is no public URL.
 */
class MediaLibraryController extends Controller
{
    public function __construct(private SocialMediaLibraryService $media) {}

    public function index(Request $r)
    {
        return view('marketer.media.index', $this->media->libraryData($r->query()));
    }

    public function show(int $id)
    {
        $a = SocialMediaAsset::query()->with(['product:id,name,slug,sku', 'versions.uploader:id,name', 'creator:id,name', 'reviewer:id,name'])->findOrFail($id);

        return view('marketer.media.show', ['a' => $a]);
    }

    public function store(SocialMediaAssetRequest $r): JsonResponse
    {
        return $this->run(function () use ($r) {
            $a = $this->media->create(Product::query()->findOrFail($r->integer('product_id')), $r->assetData(), $r->file('file'), $r->user('admin'));

            return $this->ok(__('marketing.media.done.created'), $r->boolean('stay') ? url()->previous() : route('marketer.media.show', $a->id), ['id' => $a->id]);
        });
    }

    public function update(SocialMediaAssetRequest $r, int $id): JsonResponse
    {
        return $this->run(function () use ($r, $id) {
            $this->media->update($this->find($id), $r->assetData());

            return $this->ok(__('marketing.media.done.saved'), route('marketer.media.show', $id));
        });
    }

    public function version(SocialMediaAssetRequest $r, int $id): JsonResponse
    {
        return $this->run(function () use ($r, $id) {
            $a = $this->media->addVersion($this->find($id), $r->file('file'), $r->assetData(), $r->user('admin'));

            return $this->ok(__('marketing.media.done.version', ['v' => $a->version]), route('marketer.media.show', $id));
        });
    }

    public function submit(int $id): JsonResponse
    {
        return $this->run(fn () => $this->media->submit($this->find($id)) ? $this->ok(__('marketing.media.done.submitted')) : null);
    }

    public function approve(MediaDecisionRequest $r, int $id): JsonResponse
    {
        return $this->run(fn () => $this->media->approve($this->find($id), $r->user('admin'), $r->input('note')) ? $this->ok(__('marketing.media.done.approved')) : null);
    }

    public function reject(MediaDecisionRequest $r, int $id): JsonResponse
    {
        return $this->run(fn () => $this->media->reject($this->find($id), $r->user('admin'), $r->input('note')) ? $this->ok(__('marketing.media.done.rejected'), url()->previous()) : null);
    }

    public function archive(int $id): JsonResponse
    {
        return $this->run(fn () => $this->media->archive($this->find($id)) ? $this->ok(__('marketing.media.done.archived')) : null);
    }

    public function destroy(int $id): JsonResponse
    {
        $a = $this->find($id);
        $this->media->delete($a);

        return $this->ok(__('marketing.media.done.deleted'), route('marketer.media.index', ['product_id' => $a->product_id]));
    }

    public function file(Request $r, int $id): Response
    {
        return $this->media->fileResponse($this->find($id), $r->filled('v') ? $r->integer('v') : null);
    }

    public function thumb(int $id): Response
    {
        return $this->media->fileResponse($this->find($id), null, true);
    }

    public function download(Request $r, int $id): Response
    {
        return $this->media->fileResponse($this->find($id), $r->filled('v') ? $r->integer('v') : null, false, true);
    }

    /** Signed, short-lived preview (MCP / API consumers): no session; the signature is the authorisation (route middleware `signed`). */
    public function signedPreview(Request $r, int $id): Response
    {
        $a = $this->find($id);
        abort_if($r->integer('v') !== $a->version, 404);   // a URL is valid for the version it was issued for only

        return $this->media->fileResponse($a, null, $r->boolean('thumb'));
    }

    private function find(int $id): SocialMediaAsset
    {
        return SocialMediaAsset::query()->findOrFail($id);
    }

    private function run(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (MarketingActionException $e) {
            return response()->json(['success' => false, 'error' => $e->errorCode, 'message' => $e->getMessage(), 'errors' => $e->errors ?: null, 'data' => $e->data ?: null], $e->status);
        }
    }
}
