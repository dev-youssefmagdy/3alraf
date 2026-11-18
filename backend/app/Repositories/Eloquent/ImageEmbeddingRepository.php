<?php

namespace App\Repositories\Eloquent;

use App\Enums\ImageEmbeddingSource;
use App\Models\ImageEmbedding;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Contracts\ImageEmbeddingRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** image_embeddings + the catalog sources it indexes. Every write that changes a vector or removes rows bumps a random version token (search-index cache key). */
class ImageEmbeddingRepository extends BaseRepository implements ImageEmbeddingRepositoryInterface
{
    private const VERSION_KEY = 'image_search:version';

    protected string $model = ImageEmbedding::class;

    public function findSource(ImageEmbeddingSource $type, int $id, string $model): ?ImageEmbedding
    {
        return $this->query()->where(['source_type' => $type->value, 'source_id' => $id, 'model' => $model])->first();
    }

    public function put(ImageEmbeddingSource $type, int $id, string $model, array $values): ImageEmbedding
    {
        $keys = ['source_type' => $type->value, 'source_id' => $id, 'model' => $model];
        try {
            $row = $this->query()->updateOrCreate($keys, $values);
        } catch (QueryException $e) { // two writers created the same row at once: the second one updates it
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            $row = $this->query()->updateOrCreate($keys, $values);
        }
        if (array_key_exists('vector', $values)) { // status-only writes (pending / failed) do not change what search sees
            $this->bumpVersion();
        }

        return $row;
    }

    public function create(array $data): Model
    {
        return tap(parent::create($data), fn () => $this->bumpVersion());
    }

    public function update(Model $model, array $data): Model
    {
        return tap(parent::update($model, $data), fn () => $this->bumpVersion());
    }

    public function delete(Model $model): bool
    {
        return tap(parent::delete($model), fn () => $this->bumpVersion());
    }

    public function deleteSources(ImageEmbeddingSource $type, array $ids): int
    {
        if (! $ids) {
            return 0;
        }
        $n = 0;
        foreach (array_chunk(array_values(array_unique($ids)), 500) as $chunk) {
            $n += $this->query()->where('source_type', $type->value)->whereIn('source_id', $chunk)->delete();
        }
        $n && $this->bumpVersion();

        return $n;
    }

    public function sources(): Collection
    {
        $productType = (new Product)->getMorphClass();
        $variantType = (new ProductVariant)->getMorphClass();

        $productImages = DB::table('images as i')->join('products as p', 'p.id', '=', 'i.imageable_id')
            ->where('i.imageable_type', $productType)->whereNull('p.deleted_at')
            ->selectRaw("'image' as type, i.id, i.path, p.id as product_id, NULL as variant_id, NULL as bundle_id");
        $variantImages = DB::table('images as i')->join('product_variants as v', 'v.id', '=', 'i.imageable_id')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('i.imageable_type', $variantType)->whereNull('v.deleted_at')->whereNull('p.deleted_at')
            ->selectRaw("'image' as type, i.id, i.path, p.id as product_id, v.id as variant_id, NULL as bundle_id");
        $bundles = DB::table('bundles')->whereNull('deleted_at')->whereNotNull('image')->where('image', '!=', '')->where('image', 'not like', 'http%') /* remote URLs are not on our disk */
            ->selectRaw("'bundle' as type, id, image as path, NULL as product_id, NULL as variant_id, id as bundle_id");

        return $productImages->unionAll($variantImages)->unionAll($bundles)->get()
            ->map(fn ($r) => ['type' => ImageEmbeddingSource::from($r->type), 'id' => (int) $r->id, 'path' => (string) $r->path,
                'product_id' => $r->product_id !== null ? (int) $r->product_id : null, 'variant_id' => $r->variant_id !== null ? (int) $r->variant_id : null,
                'bundle_id' => $r->bundle_id !== null ? (int) $r->bundle_id : null]);
    }

    public function rowsByKey(string $model, ?Collection $only = null): Collection
    {
        return $this->query()->where('model', $model)
            ->when($only !== null, fn ($q) => $q->where(fn ($w) => $only->groupBy(fn ($s) => $s['type']->value)
                ->each(fn ($group, $type) => $w->orWhere(fn ($x) => $x->where('source_type', $type)->whereIn('source_id', $group->pluck('id')->all())))
                ->whenEmpty(fn () => $w->whereRaw('1 = 0'))))
            ->get(['id', 'source_type', 'source_id', 'source_path', 'source_hash', 'status', 'queued_at', 'failed_at', 'error'])
            ->keyBy(fn (ImageEmbedding $e) => $e->source_type->value.':'.$e->source_id);
    }

    public function orphans(): array
    {
        $images = DB::table('image_embeddings as e')->leftJoin('images as i', 'i.id', '=', 'e.source_id')
            ->where('e.source_type', ImageEmbeddingSource::IMAGE->value)->whereNull('i.id')->distinct()->pluck('e.source_id')->all();
        $bundles = DB::table('image_embeddings as e')->leftJoin('bundles as b', 'b.id', '=', 'e.source_id')
            ->where('e.source_type', ImageEmbeddingSource::BUNDLE->value)
            ->where(fn ($w) => $w->whereNull('b.id')->orWhereNull('b.image')->orWhere('b.image', ''))->distinct()->pluck('e.source_id')->all();

        return [ImageEmbeddingSource::IMAGE->value => array_map('intval', $images), ImageEmbeddingSource::BUNDLE->value => array_map('intval', $bundles)];
    }

    public function searchIndex(string $model, int $dim): array
    {
        $rows = DB::table('image_embeddings as e')
            ->leftJoin('products as p', 'p.id', '=', 'e.product_id')
            ->leftJoin('product_variants as v', 'v.id', '=', 'e.product_variant_id')
            ->leftJoin('bundles as b', 'b.id', '=', 'e.bundle_id')
            ->where('e.model', $model)->where('e.dim', $dim)->whereNotNull('e.vector')
            ->where(fn ($w) => $w
                ->where(fn ($i) => $i->where('e.source_type', ImageEmbeddingSource::IMAGE->value)->whereNotNull('p.id')->whereNull('p.deleted_at')
                    ->where(fn ($v) => $v->whereNull('e.product_variant_id')->orWhere(fn ($x) => $x->whereNotNull('v.id')->whereNull('v.deleted_at'))))
                ->orWhere(fn ($b) => $b->where('e.source_type', ImageEmbeddingSource::BUNDLE->value)->whereNotNull('b.id')->whereNull('b.deleted_at')))
            ->orderBy('e.id')
            ->get(['e.source_type', 'e.source_id', 'e.product_id', 'e.product_variant_id', 'e.bundle_id', 'e.vector']);

        $bytes = $dim * 4;
        $blob = '';
        $map = [];
        foreach ($rows as $r) {
            if (strlen((string) $r->vector) !== $bytes) {
                continue;
            }
            $blob .= $r->vector;
            $map[] = [$r->source_type, (int) $r->source_id, $r->product_id !== null ? (int) $r->product_id : null,
                $r->product_variant_id !== null ? (int) $r->product_variant_id : null, $r->bundle_id !== null ? (int) $r->bundle_id : null];
        }

        return ['blob' => $blob, 'map' => $map, 'dim' => $dim];
    }

    public function version(): string
    {
        return (string) Cache::rememberForever(self::VERSION_KEY, fn () => Str::random(16));
    }

    public function bumpVersion(): void
    {
        Cache::forever(self::VERSION_KEY, Str::random(16));
    }
}
