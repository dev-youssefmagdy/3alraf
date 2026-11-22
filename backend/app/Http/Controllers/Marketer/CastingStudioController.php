<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\CastingLiveProduct;
use App\Models\ModelGenerationHistory;
use App\Models\ModelPosePreset;
use App\Models\Product;
use App\Models\VirtualModel;
use App\Services\Casting\AdStudioService;
use App\Services\Casting\CastingStorage;
use App\Services\Casting\LivePushService;
use App\Services\Casting\ProviderManager;
use App\Services\Casting\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Ad workflow (/marketer/casting/studio): approved model + product + creative choices → queued image / video, review, hand-off to the private social library. */
class CastingStudioController extends Controller
{
    public function __construct(private AdStudioService $ads, private ProviderManager $providers) {}

    public function index(Request $r)
    {
        return view('marketer.casting.studio', [
            'models' => VirtualModel::query()->where('status', 'approved')->orderBy('name')->get(['id', 'name', 'age_range', 'gender']),
            'products' => Product::query()->orderByDesc('id')->limit(1500)->get(['id', 'name', 'sku']), 'liveProducts' => CastingLiveProduct::query()->orderByDesc('id')->limit(500)->get(),
            'poses' => ModelPosePreset::query()->where('type', 'pose')->where('is_active', true)->orderBy('sort_order')->get(),
            'cameras' => ModelPosePreset::query()->where('type', 'camera')->where('is_active', true)->orderBy('sort_order')->get(),
            'providers' => $this->providers->selectable(), 'caps' => $this->ads->capabilities(), 'formats' => AdStudioService::FORMATS,
            'history' => ModelGenerationHistory::query()->whereIn('kind', ['ad_image', 'ad_video'])->with(['virtualModel:id,name', 'product:id,name', 'liveProduct'])->latest('id')->limit(24)->get(),
            'pick' => ['model' => (int) $r->query('model'), 'product' => (int) $r->query('product')],
            'canApprove' => $r->user('marketer')->can('ai_casting.approve'), 'livePush' => LivePushService::configured(),
        ]);
    }

    public function store(Request $r): JsonResponse
    {
        $v = $r->validate(['model_id' => ['required', 'integer', 'exists:virtual_models,id'], 'product_id' => ['required_without:live_product_id', 'nullable', 'integer', 'exists:products,id'], 'live_product_id' => ['nullable', 'integer', 'exists:casting_live_products,id'], 'concept' => ['nullable', 'string', 'max:300'],
            'scene' => ['nullable', 'string', 'max:200'], 'outfit' => ['nullable', 'string', 'max:200'], 'background' => ['nullable', 'string', 'max:200'], 'campaign' => ['nullable', 'string', 'max:120'],
            'pose' => ['nullable', 'string', 'max:60'], 'camera' => ['nullable', 'string', 'max:60'], 'format' => ['nullable', 'string', 'max:10'], 'provider' => ['nullable', 'string', 'max:20']]);
        $h = $this->ads->createImage(VirtualModel::findOrFail($v['model_id']), Subject::resolve($v['product_id'] ?? null, $v['live_product_id'] ?? null), $v, $r->user('marketer'));

        return response()->json(['id' => $h->id, 'status' => $h->status], 202);
    }

    public function status(int $id): JsonResponse
    {
        $h = ModelGenerationHistory::findOrFail($id);

        return response()->json(['id' => $h->id, 'status' => $h->status, 'error' => $h->error, 'kind' => $h->kind, 'attempts' => $h->attempts, 'in_library' => (bool) $h->social_media_asset_id,
            'fidelity' => $h->params['product_fidelity'] ?? null, 'elapsed' => $h->status === 'running' ? (int) now()->diffInSeconds($h->updated_at, true) : null, 'has_output' => (bool) $h->getRawOriginal('output_path')]);
    }

    public function image(int $id): Response
    {
        $path = ModelGenerationHistory::findOrFail($id)->getRawOriginal('output_path');
        abort_unless($path && CastingStorage::disk()->exists($path), 404);

        return CastingStorage::disk()->response($path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=300']);
    }

    public function video(Request $r, int $id): JsonResponse
    {
        $r->validate(['motion' => ['nullable', 'string', 'max:300']]);
        $h = $this->ads->createVideo(ModelGenerationHistory::findOrFail($id), $r->user('marketer'), $r->input('motion'));

        return response()->json(['id' => $h->id, 'status' => $h->status], 202);
    }

    public function toLibrary(Request $r, int $id): JsonResponse
    {
        return response()->json(['asset_id' => $this->ads->sendToLibrary(ModelGenerationHistory::findOrFail($id), $r->user('marketer'))]);
    }

    /** Explicit hand-off of a finished generation of a LIVE product to 3alraf.com (private library, pending review there). */
    public function pushLive(Request $r, int $id, LivePushService $push): JsonResponse
    {
        try {
            $res = $push->push(ModelGenerationHistory::findOrFail($id));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['asset_id' => $res['asset_id'], 'review_url' => $res['review_url'], 'duplicate' => $res['duplicate']]);
    }
}
