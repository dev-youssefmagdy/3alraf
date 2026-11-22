<?php

namespace App\Services\Casting;

use App\Jobs\Casting\GenerateModelImageJob;
use App\Models\Marketer;
use App\Models\ModelGenerationHistory;
use App\Models\ModelPosePreset;
use App\Models\CastingLiveProduct;
use App\Models\Product;
use App\Models\VirtualModel;
use App\Services\Casting\Providers\ComfyUiProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Video Studio: image-to-video from (a) a finished ad image of a virtual model or (b) a product photo (showcase).
 * The source is always COPIED to the private disk (fitted onto the target canvas without cropping); the product's own image is never modified.
 * Settings (sizes, frame count, fps, steps) come only from the configured, verified profile. One GPU job at a time (shared `casting-gpu` lock).
 */
class VideoStudioService
{
    public const MODES = ['model_ad', 'product_showcase'];

    public function __construct(private ProviderManager $providers, private CastingStorage $storage) {}

    /** What the UI may offer, from the real provider + profile. */
    public function status(?string $provider = null): array
    {
        $p = $this->providers->make($provider);
        $profile = VideoProfile::current();
        $base = ['provider' => $p->name(), 'profile' => $profile['key'], 'label' => $profile['label'] ?? $profile['key'], 'lengths' => $profile['lengths'], 'fps' => $profile['fps'], 'sizes' => $profile['sizes'],
            'durations' => collect($profile['lengths'])->mapWithKeys(fn ($n) => [$n => round($n / max(1, $profile['fps']), 1)])->all()];
        if ($p instanceof ComfyUiProvider) {
            $r = $p->videoReadiness();

            return $base + ['ready' => $r['ready'], 'missing' => $r['missing'], 'verified' => $r['verified']];
        }

        return $base + ['ready' => false, 'missing' => ['provider has no video support'], 'verified' => false];
    }

    /** Auto prompt from the chosen presets; the marketer can edit it before generating. */
    public function buildPrompt(string $mode, ?string $productName, ?VirtualModel $model, array $sel): string
    {
        $frag = fn (string $type, ?string $key) => $key ? ModelPosePreset::query()->where('type', $type)->where('is_active', true)->where('key', $key)->value('prompt_fragment') : null;
        $subject = $mode === 'model_ad'
            ? 'the same person as in the image'.($model?->name ? '' : '').', natural subtle lifelike movement, same face and identity'
            : 'product showcase of '.($productName ? '"'.$productName.'"' : 'the product').', clean commercial look';
        $parts = [$subject, $frag('pmove', $sel['pmove'] ?? null), $frag('motion', $sel['motion'] ?? null), $frag('scene', $sel['scene'] ?? null),
            ! empty($sel['note']) ? Str::limit(trim(strip_tags((string) $sel['note'])), 200, '') : null,
            'the product keeps its exact real shape, colours, label and packaging; no new logos or text; smooth natural motion, professional advertising video'];

        return implode(', ', array_filter($parts));
    }

    /** @param array{prompt?:string,format?:string,length?:int,seed?:int,motion?:string,pmove?:string,scene?:string,note?:string,provider?:string} $v */
    public function createFromGeneration(ModelGenerationHistory $img, array $v, Marketer $by): ModelGenerationHistory
    {
        if ($img->status !== 'succeeded' || $img->kind !== 'ad_image' || ! $img->output_path) {
            throw ValidationException::withMessages(['generation' => __('casting.errors.not_ready')]);
        }
        $bytes = (string) CastingStorage::disk()->get($img->output_path);

        return $this->create('model_ad', $bytes, $img->subject(), $img->virtualModel, $v, $by, $img->provider, ['source_id' => $img->id, 'source_kind' => 'generation']);
    }

    public function createFromProduct(Product|CastingLiveProduct|Subject $product, array $v, Marketer $by): ModelGenerationHistory
    {
        $x = Subject::of($product);
        $bytes = $x->imageBytes();
        if (! $bytes) {
            throw ValidationException::withMessages(['product_id' => __('casting.errors.no_product_image')]);
        }

        return $this->create('product_showcase', (string) $bytes, $x, null, $v, $by, $v['provider'] ?? null, ['source_kind' => 'product_image']);
    }

    private function create(string $mode, string $bytes, Subject $product, ?VirtualModel $model, array $v, Marketer $by, ?string $provider, array $source): ModelGenerationHistory
    {
        $status = $this->status($provider);
        if (! $status['ready']) {
            throw ValidationException::withMessages(['video' => __('casting.errors.video_unavailable', ['why' => implode('; ', $status['missing'])])]);
        }
        $profile = VideoProfile::current();
        $format = array_key_exists($v['format'] ?? '', $profile['sizes']) ? $v['format'] : array_key_first($profile['sizes']);
        [$w, $h] = $profile['sizes'][$format];
        $length = in_array((int) ($v['length'] ?? 0), $profile['lengths'], true) ? (int) $v['length'] : $profile['lengths'][0];
        $sel = array_intersect_key($v, array_flip(['motion', 'pmove', 'scene', 'note']));
        $prompt = trim(strip_tags((string) ($v['prompt'] ?? ''))) ?: $this->buildPrompt($mode, $product->name(), $model, $sel);
        $prompt = Str::limit($prompt, 900, '');
        $seed = isset($v['seed']) && (int) $v['seed'] > 0 ? (int) $v['seed'] : random_int(1, 2_000_000_000);

        $fitted = $this->fit($bytes, $w, $h);
        $params = ['mode' => $mode, 'format' => $format, 'width' => $w, 'height' => $h, 'length' => $length, 'fps' => $profile['fps'], 'steps' => $profile['steps'],
            'profile' => $profile['key'], 'campaign' => null] + $sel + $source;
        $dupe = ModelGenerationHistory::query()->where('kind', 'ad_video')->whereIn('status', ['queued', 'running'])->where('product_id', $product->productId())->where('live_product_id', $product->liveProductId())->where('prompt', $prompt)
            ->where('seed', $seed)->where('virtual_model_id', $model?->id)->first();
        if ($dupe) {
            return $dupe;
        }
        $f = $this->storage->storeBytes('casting/video-sources', $fitted);
        $params['source_path'] = $f['path'];

        $h = ModelGenerationHistory::create(['virtual_model_id' => $model?->id, 'product_id' => $product->productId(), 'live_product_id' => $product->liveProductId(), 'kind' => 'ad_video', 'provider' => $provider ?: config('casting.provider'),
            'status' => 'queued', 'prompt' => $prompt, 'seed' => $seed, 'params' => $params, 'job_token' => $token = Str::random(32), 'created_by' => $by->id]);
        GenerateModelImageJob::dispatch($h->id, $token);

        return $h;
    }

    /** Retry a failed video: same settings and source, fresh token (an older in-flight job becomes a no-op). */
    public function retry(ModelGenerationHistory $h): ModelGenerationHistory
    {
        if ($h->kind !== 'ad_video' || $h->status !== 'failed') {
            throw ValidationException::withMessages(['generation' => __('casting.errors.not_ready')]);
        }
        $h->update(['status' => 'queued', 'error' => null, 'attempts' => 0, 'job_token' => $token = Str::random(32)]);
        GenerateModelImageJob::dispatch($h->id, $token);

        return $h->refresh();
    }

    /** Fit the whole image inside w×h (no cropping, so the product is never cut), padding with the image's own border colour. Returns PNG bytes. */
    public function fit(string $bytes, int $w, int $h): string
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            throw ValidationException::withMessages(['file' => __('casting.errors.bad_image')]);
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min($w / $sw, $h / $sh);
        $nw = max(1, (int) round($sw * $scale));
        $nh = max(1, (int) round($sh * $scale));
        $dst = imagecreatetruecolor($w, $h);
        $rgb = imagecolorat($src, 2, 2);
        imagefill($dst, 0, 0, imagecolorallocate($dst, ($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255));
        imagecopyresampled($dst, $src, intdiv($w - $nw, 2), intdiv($h - $nh, 2), 0, 0, $nw, $nh, $sw, $sh);
        ob_start();
        imagepng($dst);
        $out = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return $out;
    }
}
