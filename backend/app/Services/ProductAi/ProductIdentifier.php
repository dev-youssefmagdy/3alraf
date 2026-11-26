<?php

namespace App\Services\ProductAi;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Llm\FreeLlmClient;
use App\Services\Llm\LlmUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use Throwable;

/**
 * A1 "Price list by image", step 1: a vision model reads the photo → {brand, name, model, size, search_queries[]}.
 * The photo is re-encoded as a JPEG of at most 1024 px (catalog images are AVIF, which vision models rarely accept).
 * Cached 24 h by the hash of the photo. The search itself is never started here: the admin confirms the search text first.
 */
class ProductIdentifier
{
    private const PROMPT = <<<'TXT'
Identify the product in this photo so it can be searched for in Egyptian online shops.
Read the brand, product name, model number and size / capacity / volume from the product or its packaging.
Do not guess: use null for anything you cannot see or are not sure about.
search_queries: 1 to 5 short web search texts for this exact product (brand + model works best), English first, then Arabic if useful.
Answer with JSON only: {"brand":string|null,"name":string|null,"model":string|null,"size":string|null,"search_queries":[string]}
TXT;

    public function __construct(private FreeLlmClient $llm) {}

    /**
     * @return array{brand:?string,name:?string,model:?string,size:?string,search_queries:list<string>,found:bool,answered_by:?string,cached_at:?string}
     *
     * @throws LlmUnavailableException
     * @throws InvalidArgumentException the bytes are not a readable image
     */
    public function identify(string $bytes): array
    {
        $key = 'product_ai:identify:'.hash('sha256', $bytes);
        if (is_array($hit = Cache::get($key))) {
            return ['cached_at' => $hit['computed_at']] + $hit;
        }
        $jpeg = self::prepare($bytes);

        [$data, $response] = $this->llm->chatJson([['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => self::PROMPT],
            FreeLlmClient::imagePart($jpeg, 'image/jpeg'),
        ]]], 'vision', fn (array $json) => self::validate($json), ['max_tokens' => 400]);

        $out = $data + ['answered_by' => $response->answeredBy(), 'computed_at' => now()->toIso8601String(), 'cached_at' => null];
        if ($out['found']) {
            Cache::put($key, $out, now()->addHours((int) config('product_ai.cache_hours', 24)));
        }

        return $out;
    }

    /** The stored main photo of a variant (else of its product), or of the product. Null when there is none. */
    public static function storedImage(Product $product, ?ProductVariant $variant = null): ?string
    {
        $image = $variant?->images()->first() ?? $product->images()->first();
        if (! $image) {
            return null;
        }
        $disk = Storage::disk(config('media.disk'));
        foreach (array_filter([$image->path, $image->thumb_path]) as $path) {
            if ($disk->exists($path)) {
                return $disk->get($path);
            }
        }

        return null;
    }

    /**
     * Validated, cleaned identification; null when the answer has the wrong shape.
     *
     * @return array{brand:?string,name:?string,model:?string,size:?string,search_queries:list<string>,found:bool}|null
     */
    public static function validate(array $json): ?array
    {
        if (array_is_list($json)) {
            return null;
        }
        $field = function (mixed $v): ?string {
            $t = is_scalar($v) ? WebSearchService::clean($v, 120) : '';

            return $t === '' || in_array(mb_strtolower($t), ['null', 'unknown', 'n/a', 'none', 'غير معروف'], true) ? null : $t;
        };
        $out = ['brand' => $field($json['brand'] ?? null), 'name' => $field($json['name'] ?? null), 'model' => $field($json['model'] ?? null), 'size' => $field($json['size'] ?? null)];
        $queries = [];
        foreach ((array) ($json['search_queries'] ?? []) as $q) {
            $q = is_string($q) ? WebSearchService::clean($q, 200) : '';
            if (mb_strlen($q) >= 3 && ! in_array(mb_strtolower($q), array_map('mb_strtolower', $queries), true)) {
                $queries[] = $q;
            }
        }
        if ($queries === [] && ($out['name'] || $out['model'])) {
            $queries[] = trim(implode(' ', array_filter([$out['brand'], $out['name'], $out['model']])));
        }

        return $out + ['search_queries' => array_slice($queries, 0, 5), 'found' => $queries !== [] || $out['brand'] !== null || $out['name'] !== null];
    }

    /** @throws InvalidArgumentException */
    public static function prepare(string $bytes): string
    {
        try {
            $img = (new ImageManager(new Driver))->decodeBinary($bytes);

            return (string) $img->scaleDown(1024, 1024)->encodeUsingFormat(Format::JPEG, quality: 85);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('unreadable image: '.$e->getMessage(), 0, $e);
        }
    }
}
