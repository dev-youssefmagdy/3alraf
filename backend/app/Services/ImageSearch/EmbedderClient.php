<?php

namespace App\Services\ImageSearch;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client of the local embedder service (image-embedder/, 127.0.0.1 only). Base URL = config image_search.embedder.url (IMAGE_EMBEDDER_URL).
 * Sends image bytes, gets {model, dim, vector: base64 float32}. Checks that model and dim match config so vectors of two models never mix.
 */
class EmbedderClient
{
    private function http()
    {
        return Http::baseUrl((string) config('image_search.embedder.url'))
            ->timeout((float) config('image_search.embedder.timeout', 15))
            ->connectTimeout((float) config('image_search.embedder.connect_timeout', 2))
            ->acceptJson();
    }

    /**
     * @throws EmbedderUnavailableException  down / timeout / 5xx / unexpected answer
     * @throws InvalidImageException         4xx: the bytes are not a usable image
     */
    public function embed(string $bytes, string $filename = 'image'): EmbeddingResult
    {
        try {
            $res = $this->http()->attach('image', $bytes, $filename)->post('/embed');
        } catch (ConnectionException $e) {
            throw new EmbedderUnavailableException('embedder unreachable: '.$e->getMessage(), 0, $e);
        }
        if ($res->status() >= 400 && $res->status() < 500) {
            throw new InvalidImageException((string) ($res->json('error') ?? 'rejected').': '.mb_substr((string) $res->json('detail'), 0, 200));
        }
        if (! $res->successful()) {
            throw new EmbedderUnavailableException('embedder answered HTTP '.$res->status());
        }

        $model = (string) $res->json('model');
        $dim = (int) $res->json('dim');
        $vector = base64_decode((string) $res->json('vector'), true);
        if ($model !== (string) config('image_search.model') || $dim !== (int) config('image_search.dim') || $vector === false || strlen($vector) !== $dim * 4) {
            throw new EmbedderUnavailableException("embedder answered model \"$model\" dim $dim; expected \"".config('image_search.model').'" dim '.config('image_search.dim'));
        }

        return new EmbeddingResult($model, $dim, $vector);
    }

    /** Health of the embedder (cached a few seconds): ['up' => bool, 'model' => ?string]. Never throws. */
    public function health(bool $fresh = false): array
    {
        $check = function () {
            try {
                $res = $this->http()->timeout(2)->get('/health');

                return ['up' => $res->successful() && $res->json('model') === config('image_search.model'), 'model' => $res->json('model')];
            } catch (\Throwable) {
                return ['up' => false, 'model' => null];
            }
        };

        return $fresh ? $check() : Cache::remember('image_search:health', (int) config('image_search.embedder.health_cache_seconds', 30), $check);
    }
}
