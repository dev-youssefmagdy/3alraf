<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\CastingLiveProduct;
use App\Models\ModelGenerationHistory;
use App\Models\ModelPosePreset;
use App\Models\Product;
use App\Services\Casting\CastingStorage;
use App\Services\Casting\LivePushService;
use App\Services\Casting\Subject;
use App\Services\Casting\VideoStudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Video Studio (/marketer/casting/video): image-to-video from an ad image or a product photo; progress, preview, retry; library hand-off reuses generations.library. */
class CastingVideoController extends Controller
{
    public function __construct(private VideoStudioService $video) {}

    public function index(Request $r)
    {
        $presets = fn (string $t) => ModelPosePreset::query()->where('type', $t)->where('is_active', true)->orderBy('sort_order')->get();

        return view('marketer.casting.video', [
            'status' => $this->video->status(),
            'products' => Product::query()->orderByDesc('id')->limit(1500)->get(['id', 'name', 'sku']), 'liveProducts' => CastingLiveProduct::query()->orderByDesc('id')->limit(500)->get(),
            'images' => ModelGenerationHistory::query()->where('kind', 'ad_image')->where('status', 'succeeded')->with(['virtualModel:id,name', 'product:id,name', 'liveProduct'])->latest('id')->limit(60)->get(),
            'motions' => $presets('motion'), 'pmoves' => $presets('pmove'), 'scenes' => $presets('scene'),
            'history' => ModelGenerationHistory::query()->where('kind', 'ad_video')->with(['virtualModel:id,name', 'product:id,name', 'liveProduct'])->latest('id')->limit(24)->get(),
            'canApprove' => $r->user('marketer')->can('ai_casting.approve'), 'livePush' => LivePushService::configured(), 'pick' => (int) $r->query('generation'),
        ]);
    }

    private function rules(): array
    {
        return ['mode' => ['required', 'in:model_ad,product_showcase'], 'product_id' => ['nullable', 'integer', 'exists:products,id'], 'live_product_id' => ['nullable', 'integer', 'exists:casting_live_products,id'], 'generation_id' => ['nullable', 'integer', 'exists:model_generation_history,id'],
            'prompt' => ['nullable', 'string', 'max:900'], 'format' => ['nullable', 'string', 'max:10'], 'length' => ['nullable', 'integer'], 'seed' => ['nullable', 'integer', 'min:0'],
            'motion' => ['nullable', 'string', 'max:60'], 'pmove' => ['nullable', 'string', 'max:60'], 'scene' => ['nullable', 'string', 'max:60'], 'note' => ['nullable', 'string', 'max:200']];
    }

    /** Auto-built prompt for the editor. */
    public function prompt(Request $r): JsonResponse
    {
        $v = $r->validate($this->rules());
        $gen = ! empty($v['generation_id']) ? ModelGenerationHistory::with('virtualModel', 'product', 'liveProduct')->find($v['generation_id']) : null;
        $name = $gen?->productLabel() ?: (! empty($v['live_product_id']) ? CastingLiveProduct::find($v['live_product_id'])?->displayName() : (! empty($v['product_id']) ? Product::find($v['product_id'])?->name : null));

        return response()->json(['prompt' => $this->video->buildPrompt($v['mode'], $name, $gen?->virtualModel, $v)]);
    }

    public function store(Request $r): JsonResponse
    {
        $v = $r->validate($this->rules());
        if ($v['mode'] === 'model_ad') {
            $r->validate(['generation_id' => ['required']]);
            $h = $this->video->createFromGeneration(ModelGenerationHistory::findOrFail($v['generation_id']), $v, $r->user('marketer'));
        } else {
            $r->validate(['product_id' => ['required_without:live_product_id']]);
            $h = $this->video->createFromProduct(Subject::resolve($v['product_id'] ?? null, $v['live_product_id'] ?? null), $v, $r->user('marketer'));
        }

        return response()->json(['id' => $h->id, 'status' => $h->status], 202);
    }

    public function retry(Request $r, int $id): JsonResponse
    {
        $h = $this->video->retry(ModelGenerationHistory::findOrFail($id));

        return response()->json(['id' => $h->id, 'status' => $h->status]);
    }

    /** The (fitted) source frame of a video, streamed from the private disk. */
    public function source(int $id): Response
    {
        $path = (string) (ModelGenerationHistory::findOrFail($id)->params['source_path'] ?? '');
        abort_unless($path !== '' && CastingStorage::disk()->exists($path), 404);

        return CastingStorage::disk()->response($path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=300']);
    }
}
