<?php

namespace App\Services\Casting;

use App\Models\ModelGenerationHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Sends ONE approved local generation to 3alraf.com (POST /api/internal/marketing/media/upload, scope media:write). The file goes straight from this machine
 * to the live API over https (never through the chat model). It lands there as `pending_review` in the PRIVATE library; a person approves it in the live
 * marketer panel. Idempotent: the same generation always sends the same client_ref, so a retry never duplicates. The token is only read from config and
 * never logged or returned.
 */
class LivePushService
{
    public static function configured(): bool
    {
        return (bool) (config('casting.live.url') && config('casting.live.token'));
    }

    public function push(ModelGenerationHistory $h): array
    {
        $h->loadMissing('liveProduct', 'virtualModel');
        if (! self::configured()) {
            throw ValidationException::withMessages(['live' => __('casting.live.push_off')]);
        }
        if ($h->status !== 'succeeded' || ! $h->output_path || ! $h->liveProduct) {
            throw ValidationException::withMessages(['generation' => __('casting.errors.not_ready')]);
        }
        $base = rtrim((string) config('casting.live.url'), '/');
        $host = (string) parse_url($base, PHP_URL_HOST);
        $local = $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.loc') || str_ends_with($host, '.test');
        if (! str_starts_with($base, 'https://') && ! $local) {
            throw new RuntimeException('LIVE_MEDIA_URL must be https.');
        }
        if (isset($h->params['live_asset_id'])) {      // already sent: report, never resend
            return ['asset_id' => $h->params['live_asset_id'], 'review_url' => $h->params['live_review_url'] ?? null, 'duplicate' => true];
        }
        $bytes = (string) CastingStorage::disk()->get($h->output_path);
        $ext = pathinfo($h->output_path, PATHINFO_EXTENSION);
        $ref = 'casting-'.$h->id.'-'.substr(hash('sha256', $h->output_path), 0, 12);
        $meta = ['origin' => 'ai_casting_local', 'generation_id' => $h->id, 'virtual_model' => $h->virtualModel?->name, 'provider' => $h->provider, 'seed' => $h->seed,
            'product_fidelity' => $h->params['product_fidelity'] ?? null];

        $res = Http::withToken((string) config('casting.live.token'))->acceptJson()->timeout((int) config('casting.live.timeout'))->connectTimeout(10)
            ->attach('file', $bytes, 'casting-'.$h->id.'.'.$ext)
            ->post($base.'/api/internal/marketing/media/upload', ['product_id' => $h->liveProduct->live_id, 'client_ref' => $ref,
                'title' => Str::limit(($h->virtualModel?->name ?? 'AI').' — '.$h->liveProduct->displayName(), 190, ''), 'tags' => 'ai-casting',
                'brief' => 'Generated locally by the AI Casting Studio. Compare with the real product photo before use: the generator does not guarantee product fidelity.',
                'generation_meta' => json_encode($meta)]);

        if (! $res->successful()) {
            $why = Str::limit((string) ($res->json('message') ?: $res->json('error') ?: 'HTTP '.$res->status()), 200, '');
            throw new RuntimeException('Live upload failed ('.$res->status().'): '.$why);
        }
        $id = (int) $res->json('data.id');
        $h->update(['params' => ($h->params ?? []) + ['live_asset_id' => $id, 'live_review_url' => $res->json('data.review_url'), 'live_pushed_at' => now()->toIso8601String()]]);

        return ['asset_id' => $id, 'review_url' => $res->json('data.review_url'), 'duplicate' => (bool) collect($res->json('warnings') ?? [])->firstWhere('code', 'duplicate')];
    }
}
