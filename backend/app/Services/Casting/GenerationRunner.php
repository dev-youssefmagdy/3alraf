<?php

namespace App\Services\Casting;

use App\Models\CastingCandidate;
use App\Models\ModelGenerationHistory;
use App\Models\ModelUsageHistory;
use App\Services\Casting\DTO\ImageRequest;
use App\Services\Casting\Exceptions\CastingProviderException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes one queued generation. Duplicate-job guard: a job only runs while the row still holds ITS token and is `queued`
 * (regenerate / a second dispatch rotates the token, so the older job becomes a no-op). Errors are stored sanitised (no URLs, no secrets).
 * Returns true when the job should be retried later.
 */
class GenerationRunner
{
    public function __construct(private ProviderManager $providers, private CastingStorage $storage) {}

    public static function clean(string $m): string
    {
        return Str::limit(preg_replace('~https?://\S+~', '[url]', $m), 500);
    }

    public function candidate(int $id, string $token, bool $final): bool
    {
        $c = CastingCandidate::query()->with('session')->find($id);
        if (! $c || $c->job_token !== $token || $c->status !== 'queued') {
            return false;
        }
        $c->update(['status' => 'generating', 'attempts' => $c->attempts + 1, 'error' => null]);
        try {
            $provider = $this->providers->make($c->session->provider);
            $img = $provider->generateImage(new ImageRequest($c->prompt, 832, 1216, $c->seed));
            $old = $c->portrait_path;
            $f = $this->storage->storeBytes('casting/candidates/'.$c->casting_session_id, $img->bytes);
            $c->update(['status' => 'ready', 'disk' => CastingStorage::diskName(), 'portrait_path' => $f['path'], 'seed' => $img->seed,
                'generation_meta' => $img->meta + ['w' => $f['width'], 'h' => $f['height'], 'identity_mode' => $provider->capabilities()['identity'] ?? 'prompt_seed']]);
            $this->storage->delete($old);

            return false;
        } catch (\Throwable $e) {
            return $this->fail($c, $e, $final);
        } finally {
            $c->session?->refreshStatus();
        }
    }

    public function history(int $id, string $token, bool $final): bool
    {
        $h = ModelGenerationHistory::query()->with('virtualModel')->find($id);
        if (! $h || $h->job_token !== $token || $h->status !== 'queued') {
            return false;
        }
        $h->update(['status' => 'running', 'attempts' => $h->attempts + 1, 'error' => null]);
        $t = microtime(true);
        try {
            $provider = $this->providers->make($h->provider);
            if ($h->kind === 'ad_video') {
                $src = CastingStorage::disk()->get((string) ($h->params['source_path'] ?? ''));
                if (! $src) {
                    throw new CastingProviderException(__('casting.errors.not_ready'), false);
                }
                $img = $provider->generateVideo((string) $src, (string) $h->prompt, $h->seed, array_intersect_key((array) $h->params, array_flip(['width', 'height', 'length', 'fps', 'steps'])));
            } else {
                [$w, $hh] = $this->size((string) ($h->params['format'] ?? '4:5'));
                $img = $provider->generateImage(new ImageRequest((string) $h->prompt, $w, $hh, $h->seed));
            }
            $f = $h->kind === 'ad_video' ? $this->storeVideo($img->bytes, $img->mime) : $this->storage->storeBytes('casting/generations/'.($h->virtual_model_id ?: 0), $img->bytes);
            $h->update(['status' => 'succeeded', 'disk' => CastingStorage::diskName(), 'output_path' => $f['path'], 'seed' => $img->seed, 'duration_ms' => (int) ((microtime(true) - $t) * 1000)]);
            if ($h->virtual_model_id && in_array($h->kind, ['ad_image', 'ad_video'], true)) {
                ModelUsageHistory::create(['virtual_model_id' => $h->virtual_model_id, 'product_id' => $h->product_id, 'generation_id' => $h->id,
                    'usage' => $h->kind, 'campaign' => $h->params['campaign'] ?? null, 'created_by' => $h->created_by]);
            }

            return false;
        } catch (\Throwable $e) {
            return $this->fail($h, $e, $final);
        }
    }

    private function storeVideo(string $bytes, string $mime): array
    {
        $ext = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime] ?? null;
        if (! $ext || $bytes === '') {
            throw new CastingProviderException(__('casting.errors.bad_video'), false);
        }
        $path = 'casting/generations/video/'.Str::random(40).'.'.$ext;
        CastingStorage::put($path, $bytes);

        return ['path' => $path];
    }

    /** Output sizes per ad format; kept ≤ 1216 px for an 8 GB GPU. */
    public function size(string $ratio): array
    {
        return match ($ratio) {
            '1:1' => [1024, 1024], '9:16' => [720, 1280], '16:9' => [1280, 720], default => [896, 1120],
        };
    }

    private function fail(CastingCandidate|ModelGenerationHistory $row, \Throwable $e, bool $final): bool
    {
        $retry = ! ($e instanceof CastingProviderException) || $e->retryable;
        $msg = self::clean($e->getMessage());
        Log::warning('casting.generation_failed', ['type' => class_basename($row), 'id' => $row->id, 'error' => $msg, 'retryable' => $retry]);
        if ($retry && ! $final) {
            $row->update(['status' => 'queued', 'error' => $msg]);

            return true;
        }
        $row->update(['status' => 'failed', 'error' => $msg]);

        return false;
    }
}
