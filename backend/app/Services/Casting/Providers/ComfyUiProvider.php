<?php

namespace App\Services\Casting\Providers;

use App\Services\Casting\Contracts\ImageProvider;
use App\Services\Casting\DTO\GeneratedImage;
use App\Services\Casting\DTO\ImageRequest;
use App\Services\Casting\Exceptions\CastingProviderException;
use App\Services\Casting\VideoProfile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Local ComfyUI HTTP API (default http://127.0.0.1:8188, no key). Workflows are built server-side from config: user input only ever reaches
 * the prompt text / seed / size, never workflow structure. Z-Image Turbo is text-to-image only, so reference images are REFUSED here
 * (capability reference_image=false) instead of being silently dropped.
 */
class ComfyUiProvider implements ImageProvider
{
    public function name(): string
    {
        return 'comfyui';
    }

    public function capabilities(): array
    {
        $c = config('casting.capabilities.comfyui');
        $c['video'] = $this->videoReadiness()['ready'];

        return $c;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('casting.comfyui.url'), '/'))->acceptJson()->timeout(30)->connectTimeout(5);
    }

    public function health(): array
    {
        try {
            $r = $this->http()->get('/system_stats');

            return $r->successful() ? ['ok' => true, 'message' => 'ComfyUI '.($r->json('system.comfyui_version') ?? '')]
                : ['ok' => false, 'message' => 'ComfyUI HTTP '.$r->status()];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => __('casting.errors.comfy_down')];
        }
    }

    public function generateImage(ImageRequest $request): GeneratedImage
    {
        if ($request->references) {
            throw CastingProviderException::unsupported('reference images (identity / product conditioning)');
        }
        $seed = $request->seed ?? random_int(1, 2_000_000_000);

        return $this->run($this->workflow($request, $seed), $seed, 'images');
    }

    /**
     * Video is ON only when the profile's workflow file exists, every model it needs is installed in ComfyUI and the profile is marked verified
     * (set after a real generated clip). Returns the reasons otherwise, so the UI can show exactly what is missing.
     *
     * @return array{ready:bool,missing:list<string>,profile:array,verified:bool}
     */
    public function videoReadiness(): array
    {
        $profile = VideoProfile::current();
        $missing = [];
        if (! is_file($profile['workflow'])) {
            $missing[] = 'workflow file';
        }
        $have = Cache::remember('casting.comfy.models', 60, function () {
            try {
                $o = $this->http()->get('/object_info')->json() ?? [];
                $list = fn ($node, $field) => (array) ($o[$node]['input']['required'][$field][0] ?? []);

                return ['unet' => $list('UNETLoader', 'unet_name'), 'clip' => $list('CLIPLoader', 'clip_name'), 'vae' => $list('VAELoader', 'vae_name'), 'nodes' => isset($o['Wan22ImageToVideoLatent'], $o['SaveVideo'], $o['CreateVideo']), 'up' => true];
            } catch (\Throwable) {
                return ['up' => false];
            }
        });
        if (! ($have['up'] ?? false)) {
            $missing[] = 'ComfyUI offline';
        } else {
            foreach ($profile['models'] as $kind => $file) {
                if (! in_array($file, $have[$kind] ?? [], true)) {
                    $missing[] = $kind.': '.$file;
                }
            }
            if (! ($have['nodes'] ?? false)) {
                $missing[] = 'nodes: Wan22ImageToVideoLatent / CreateVideo / SaveVideo';
            }
        }
        $verified = (bool) $profile['verified'];
        if (! $verified) {
            $missing[] = 'not verified yet (no real test clip recorded)';
        }

        return ['ready' => $missing === [], 'missing' => $missing, 'profile' => $profile, 'verified' => $verified];
    }

    /** @param array{width?:int,height?:int,length?:int,fps?:int,steps?:int,negative?:string} $options */
    public function generateVideo(string $imageBytes, string $prompt, ?int $seed = null, array $options = []): GeneratedImage
    {
        $r = $this->videoReadiness();
        if (! $r['ready'] && ! ($options['force'] ?? false)) {
            throw CastingProviderException::unsupported('video ('.implode('; ', $r['missing']).')');
        }
        $p = $r['profile'];
        $wf = json_decode((string) file_get_contents($p['workflow']), true);
        if (! is_array($wf)) {
            throw new CastingProviderException('Invalid video workflow JSON', false);
        }
        $this->assertGpuFree();
        $seed ??= random_int(1, 2_000_000_000);
        $name = 'casting_'.Str::random(16).'.png';
        $up = $this->http()->attach('image', $imageBytes, $name)->post('/upload/image');
        if (! $up->successful()) {
            throw new CastingProviderException('ComfyUI upload failed (HTTP '.$up->status().')');
        }
        $vars = ['image' => $up->json('name') ?? $name, 'prompt' => $prompt, 'negative' => $options['negative'] ?? $p['negative'], 'seed' => $seed,
            'width' => (int) ($options['width'] ?? 480), 'height' => (int) ($options['height'] ?? 832), 'length' => (int) ($options['length'] ?? $p['lengths'][0]),
            'fps' => (int) ($options['fps'] ?? $p['fps']), 'steps' => (int) ($options['steps'] ?? $p['steps'])] + $p['models'];

        return $this->run($this->fill($wf, $vars), $seed, 'video', (int) config('casting.comfyui.video_timeout'));
    }

    /** Replace "{{name}}" placeholders structurally (values never touch the JSON syntax): a whole-string placeholder keeps the value's type. */
    private function fill(mixed $node, array $vars): mixed
    {
        if (is_array($node)) {
            return array_map(fn ($v) => $this->fill($v, $vars), $node);
        }
        if (is_string($node) && preg_match('/^\{\{(\w+)\}\}$/', $node, $m) && array_key_exists($m[1], $vars)) {
            return $vars[$m[1]];
        }

        return $node;
    }

    /** One GPU job at a time on an 8 GB card: refuse (retryably) while ComfyUI is already executing something. */
    private function assertGpuFree(): void
    {
        $q = $this->http()->get('/queue')->json();
        if (! empty($q['queue_running'])) {
            throw new CastingProviderException(__('casting.errors.gpu_busy'));
        }
    }

    /** Z-Image Turbo text-to-image graph (same node graph as the verified local zimage workflow). */
    private function workflow(ImageRequest $r, int $seed): array
    {
        $c = config('casting.comfyui');

        return [
            '1' => ['class_type' => 'UNETLoader', 'inputs' => ['unet_name' => $c['unet'], 'weight_dtype' => 'default']],
            '2' => ['class_type' => 'CLIPLoader', 'inputs' => ['clip_name' => $c['clip'], 'type' => 'lumina2', 'device' => 'default']],
            '3' => ['class_type' => 'VAELoader', 'inputs' => ['vae_name' => $c['vae']]],
            '4' => ['class_type' => 'CLIPTextEncode', 'inputs' => ['text' => $r->prompt, 'clip' => ['2', 0]]],
            '5' => ['class_type' => 'ConditioningZeroOut', 'inputs' => ['conditioning' => ['4', 0]]],
            '6' => ['class_type' => 'ModelSamplingAuraFlow', 'inputs' => ['model' => ['1', 0], 'shift' => 3.0]],
            '7' => ['class_type' => 'EmptySD3LatentImage', 'inputs' => ['width' => $this->snap($r->width), 'height' => $this->snap($r->height), 'batch_size' => 1]],
            '8' => ['class_type' => 'KSampler', 'inputs' => ['model' => ['6', 0], 'positive' => ['4', 0], 'negative' => ['5', 0], 'latent_image' => ['7', 0],
                'seed' => $seed, 'steps' => (int) $c['steps'], 'cfg' => 1.0, 'sampler_name' => 'res_multistep', 'scheduler' => 'simple', 'denoise' => 1.0]],
            '9' => ['class_type' => 'VAEDecode', 'inputs' => ['samples' => ['8', 0], 'vae' => ['3', 0]]],
            '10' => ['class_type' => 'SaveImage', 'inputs' => ['images' => ['9', 0], 'filename_prefix' => 'casting']],
        ];
    }

    private function snap(int $v): int
    {
        return max(256, min(1536, (int) (round($v / 64) * 64)));
    }

    private function run(array $workflow, int $seed, string $outputKey, ?int $timeout = null): GeneratedImage
    {
        $started = microtime(true);
        try {
            $q = $this->http()->post('/prompt', ['prompt' => $workflow, 'client_id' => 'casting-'.Str::random(8)]);
            if (! $q->successful()) {
                $detail = Str::limit((string) ($q->json('error.message') ?? $q->json('node_errors') ? json_encode($q->json('node_errors')) : ''), 300);
                throw new CastingProviderException('ComfyUI rejected the workflow (HTTP '.$q->status().') '.$detail, $q->serverError());
            }
            $id = (string) $q->json('prompt_id');
            $deadline = time() + ($timeout ?? (int) config('casting.comfyui.timeout'));
            while (time() < $deadline) {
                $h = $this->http()->get('/history/'.$id)->json($id);
                if (is_array($h)) {
                    if (($h['status']['status_str'] ?? '') === 'error') {
                        throw new CastingProviderException('ComfyUI execution error', false);
                    }
                    foreach ($h['outputs'] ?? [] as $out) {
                        foreach (array_merge($out['images'] ?? [], $out['gifs'] ?? [], $out['videos'] ?? []) as $f) {
                            $res = $this->http()->timeout(120)->get('/view', ['filename' => $f['filename'], 'subfolder' => $f['subfolder'] ?? '', 'type' => $f['type'] ?? 'output']);
                            if ($res->successful()) {
                                return new GeneratedImage($res->body(), $res->header('Content-Type') ?: 'image/png', $seed,
                                    ['provider' => 'comfyui', 'prompt_id' => $id, 'ms' => (int) ((microtime(true) - $started) * 1000)]);
                            }
                        }
                    }
                }
                usleep(1_000_000);
            }
            throw new CastingProviderException(__('casting.errors.timeout'));
        } catch (ConnectionException) {
            throw new CastingProviderException(__('casting.errors.comfy_down'));
        }
    }
}
