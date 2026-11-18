<?php

namespace App\Services\ImageSearch;

use App\DTOs\ImageSearchQueryData;
use App\Models\Bundle;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Contracts\ImageEmbeddingRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Admin search by image (IMAGE_SEARCH_PLAN.md §3.3, §8.3). The uploaded photo is embedded by the local embedder and NEVER stored.
 * Cosine top-k in PHP over a cached float32 blob (base64 in the cache) + id map (version-stamped; never cached as unpacked PHP arrays).
 * Hits are grouped per product (product + variant images, best image wins; a variant hit is shown as that variant) or per bundle.
 * Below image_search.min_score a hit is hidden. Scores never leave this class: the UI gets strength (0–100) + a label.
 */
class ImageSearchService
{
    /** @var array{key:string, index:array}|null per-process memo */
    private static ?array $memo = null;

    public function __construct(private ImageEmbeddingRepositoryInterface $repo, private EmbedderClient $client) {}

    /**
     * @return array{results:list<array>, counts:array{all:int,products:int,bundles:int}, low_confidence:bool, took_ms:int}
     *
     * @throws EmbedderUnavailableException
     * @throws InvalidImageException
     */
    public function search(ImageSearchQueryData $q): array
    {
        $started = microtime(true);
        $query = $this->client->embed($q->bytes, $q->filename);
        $hits = $this->rank($this->score($query->floats()));
        $out = $this->present($hits, $q->type, $q->limit);
        $out['took_ms'] = (int) round((microtime(true) - $started) * 1000);
        Log::info('image_search.search', ['took_ms' => $out['took_ms'], 'results' => count($out['results']), 'type' => $q->type]);

        return $out;
    }

    /** Cached index for the current model: ['blob' => float32 bytes, 'map' => list<[type, source_id, product_id, variant_id, bundle_id]>, 'dim']. */
    public function index(): array
    {
        $model = (string) config('image_search.model');
        $version = $this->repo->version();
        $memoKey = md5($model).':'.$version;
        if (self::$memo && self::$memo['key'] === $memoKey) {
            return self::$memo['index'];
        }
        // Cached as base64 (text-only stores such as the `database` cache cannot hold raw bytes); never as unpacked PHP float arrays.
        // ONE key per model, the version stamp is stored inside: a version in the key would leave a ~5 MB orphan entry behind on every
        // change (the file store never deletes entries that are not read again), filling the disk over time.
        try {
            $store = Cache::store(config('image_search.cache_store'));
            $key = 'image_search:index:'.md5($model);
            $cached = $store->get($key);
            if (! is_array($cached) || ($cached['version'] ?? null) !== $version) {
                $i = $this->repo->searchIndex($model, (int) config('image_search.dim'));
                $cached = ['version' => $version, 'blob' => base64_encode($i['blob']), 'map' => $i['map'], 'dim' => $i['dim']];
                $store->put($key, $cached, now()->addHours((int) config('image_search.cache_ttl_hours', 24)));
            }
            $index = ['blob' => (string) base64_decode($cached['blob'], true), 'map' => $cached['map'], 'dim' => $cached['dim']];
        } catch (\Throwable $e) { // a cache failure must not break search
            report($e);
            $index = $this->repo->searchIndex($model, (int) config('image_search.dim'));
        }
        self::$memo = ['key' => $memoKey, 'index' => $index];

        return $index;
    }

    public static function flushMemo(): void
    {
        self::$memo = null;
    }

    /**
     * Cosine similarity of the query against every stored vector (both L2-normalised ⇒ dot product).
     *
     * @param  list<float>  $query
     * @return list<float>  score per index row
     */
    public function score(array $query): array
    {
        $index = $this->index();
        $dim = (int) $index['dim'];
        $n = intdiv(strlen($index['blob']), max(1, $dim * 4));
        $q = array_combine(range(1, $dim), array_slice($query, 0, $dim)); // unpack() keys start at 1
        $fmt = 'g'.$dim;
        $scores = [];
        for ($r = 0; $r < $n; $r++) {
            $s = 0.0;
            foreach (unpack($fmt, $index['blob'], $r * $dim * 4) as $k => $x) {
                $s += $x * $q[$k];
            }
            $scores[] = $s;
        }

        return $scores;
    }

    /**
     * Best hit per product / bundle at or above min_score, best first.
     *
     * @param  list<float>  $scores
     * @return list<array{score:float, kind:string, image_id:?int, product_id:?int, variant_id:?int, bundle_id:?int}>
     */
    public function rank(array $scores): array
    {
        $map = $this->index()['map'];
        $min = (float) config('image_search.min_score', 0.60);
        $best = [];
        foreach ($scores as $i => $s) {
            if ($s < $min || ! isset($map[$i])) {
                continue;
            }
            [$type, $sourceId, $productId, $variantId, $bundleId] = $map[$i];
            $key = $type === 'bundle' ? "b:$bundleId" : "p:$productId";
            if (! isset($best[$key]) || $s > $best[$key]['score']) {
                $best[$key] = ['score' => $s, 'kind' => $type === 'bundle' ? 'bundle' : ($variantId ? 'variant' : 'product'),
                    'image_id' => $type === 'bundle' ? null : $sourceId, 'product_id' => $productId, 'variant_id' => $variantId, 'bundle_id' => $bundleId];
            }
        }
        usort($best, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $best;
    }

    /** UI strength 0–100: clamp((s − min) / (full − min)). */
    public static function strength(float $s): int
    {
        $min = (float) config('image_search.min_score', 0.60);
        $full = (float) config('image_search.full_score', 0.95);

        return (int) round(max(0.0, min(1.0, ($s - $min) / max(0.01, $full - $min))) * 100);
    }

    public static function label(float $s): string
    {
        $t = (array) config('image_search.strength');

        return match (true) {
            $s >= (float) ($t['strong'] ?? 0.85) => 'strong',
            $s >= (float) ($t['medium'] ?? 0.75) => 'medium',
            default => 'weak',
        };
    }

    /**
     * Top `limit` products (variants included) and top `limit` bundles, merged best first (the modal filters them without a new upload).
     * counts = what each filter chip shows.
     */
    private function present(array $hits, string $type, int $limit): array
    {
        $products = array_values(array_filter($hits, fn ($h) => $h['kind'] !== 'bundle'));
        $bundles = array_values(array_filter($hits, fn ($h) => $h['kind'] === 'bundle'));
        $pick = match ($type) {
            'products' => array_slice($products, 0, $limit),
            'bundles' => array_slice($bundles, 0, $limit),
            default => array_merge(array_slice($products, 0, $limit), array_slice($bundles, 0, $limit)),
        };
        usort($pick, fn ($a, $b) => $b['score'] <=> $a['score']);
        $rows = $this->hydrate($pick);

        return [
            'results' => $rows,
            'counts' => ['all' => min($limit, count($rows)),
                'products' => count(array_filter($rows, fn ($r) => $r['type'] !== 'bundle')), 'bundles' => count(array_filter($rows, fn ($r) => $r['type'] === 'bundle'))],
            'low_confidence' => $hits !== [] && $hits[0]['score'] < (float) config('image_search.confident_score', 0.70),
        ];
    }

    /** Names, SKU / item count, thumbnail of the matching image and the admin link. Hits whose record vanished are dropped. */
    private function hydrate(array $hits): array
    {
        $ids = fn (string $k) => array_values(array_unique(array_filter(array_column($hits, $k))));
        $products = Product::query()->whereIn('id', $ids('product_id') ?: [0])->get(['id', 'name', 'sku'])->keyBy('id');
        $variants = ProductVariant::query()->with('values')->whereIn('id', $ids('variant_id') ?: [0])->get()->keyBy('id');
        $bundles = Bundle::query()->withCount('items')->whereIn('id', $ids('bundle_id') ?: [0])->get(['id', 'name', 'image'])->keyBy('id');
        $images = Image::query()->whereIn('id', $ids('image_id') ?: [0])->get(['id', 'path', 'thumb_path'])->keyBy('id');

        $rows = [];
        foreach ($hits as $h) {
            $base = ['strength' => self::strength($h['score']), 'match' => self::label($h['score'])];
            if ($h['kind'] === 'bundle') {
                if (! $b = $bundles->get($h['bundle_id'])) {
                    continue;
                }
                $rows[] = ['type' => 'bundle', 'id' => $b->id, 'name' => (string) $b->name, 'sku' => null, 'items' => (int) $b->items_count,
                    'thumb' => $b->imageUrl(), 'url' => route('admin.bundles.edit', $b->id)] + $base;

                continue;
            }
            if (! $p = $products->get($h['product_id'])) {
                continue;
            }
            $img = $images->get($h['image_id']);
            $v = $h['variant_id'] ? $variants->get($h['variant_id']) : null;
            if ($h['variant_id'] && ! $v) {
                continue;
            }
            $label = $v?->label();
            $rows[] = ['type' => $v ? 'variant' : 'product', 'id' => $p->id, 'variant_id' => $v?->id, 'name' => (string) $p->name.($label ? ' — '.$label : ''),
                'sku' => $v?->sku ?: $p->sku, 'items' => null, 'thumb' => $img?->thumb_url,
                'url' => route('admin.products.edit', $p->id).($v ? '#variant-section' : '')] + $base;
        }
        foreach ($rows as $i => &$r) {
            $r['best'] = $i === 0;
        }

        return $rows;
    }
}
