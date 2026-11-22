<?php

namespace App\Services;

use App\DTOs\ProductData;
use App\DTOs\ProductVariantData;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ProductVideoRepositoryInterface;
use App\Services\Qr\QrCodeManager;
use App\Support\Slug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    public const DRAFT_SKU_PREFIX = 'DRAFT-';

    public function __construct(
        private ProductRepositoryInterface $products,
        private ImageUploadService $images,
        private BarcodeService $barcodes,
        private QrCodeManager $qr,
        private ProductVideoRepositoryInterface $videos,
    ) {}

    public function create(ProductData $dto): Product
    {
        return DB::transaction(function () use ($dto) {
            $product = $this->products->create($this->payload($dto));

            return $this->finish($product, $dto);
        });
    }

    public function update(Product $product, ProductData $dto): Product
    {
        return DB::transaction(function () use ($product, $dto) {
            $product = $this->products->update($product, $this->payload($dto, $product));
            $product = $this->finish($product, $dto);
            if (! $product->is_draft) {
                $this->qr->queueCatalogProduct($product->id); // slug / variants / status / price may have changed: refresh the QR codes
            }

            return $product;
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->variants()->delete();
            $this->products->delete($product);
        });
    }

    /** Merchandising: replace the product's tags only (P5 product suggestions; the full form still goes through update()). */
    public function syncTags(Product $product, array $tagIds): Product
    {
        $this->products->syncTags($product, array_values(array_unique(array_map('intval', $tagIds))));

        return $product->refresh();
    }

    /** Merchandising: Gift Finder tags only (same fields the Gift Finder tags page writes). */
    public function updateGiftTags(Product $product, bool $suitable, array $recipients, array $occasions): Product
    {
        $product->update(['gift_suitable' => $suitable, 'gift_recipients' => array_values($recipients) ?: null, 'gift_occasions' => array_values($occasions) ?: null]);

        return $product;
    }

    /** SEO: change the slug only (unique incl. soft-deleted rows; QR codes refreshed). Returns the slug actually stored. */
    public function changeSlug(Product $product, string $slug): string
    {
        return DB::transaction(function () use ($product, $slug) {
            $product->update(['slug' => Slug::unique(Product::class, $slug, $product->name, $product->id)]);
            $this->qr->queueCatalogProduct($product->id);

            return $product->slug;
        });
    }

    /**
     * Marketing `product_update` suggestions: change ONLY the given listing columns, the category set (null = keep) and existing variants'
     * price / sale_price ([variant id => ['price' => ..., 'sale_price' => ...]]). The caller validates the values. QR codes refreshed.
     */
    public function updateListing(Product $product, array $attrs, ?array $categoryIds = null, array $variantPrices = []): Product
    {
        return DB::transaction(function () use ($product, $attrs, $categoryIds, $variantPrices) {
            if ($attrs) {
                $product = $this->products->update($product, array_intersect_key($attrs, array_flip(['name', 'description', 'brand_id', 'is_active', 'price', 'sale_price'])));
            }
            if ($categoryIds !== null) {
                $this->products->syncCategories($product, array_values(array_unique(array_map('intval', $categoryIds))));
            }
            foreach ($variantPrices as $id => $row) {
                $product->variants()->whereKey((int) $id)->first()?->update(array_intersect_key($row, array_flip(['price', 'sale_price'])));
            }
            if (! $product->is_draft) {
                $this->qr->queueCatalogProduct($product->id);
            }

            return $product->refresh();
        });
    }

    /** Append uploaded images to the gallery (after the images and videos). @param UploadedFile[] $files @return int[] the new image ids, in order */
    public function addImages(Product $product, array $files): array
    {
        $before = $product->images()->pluck('id')->all();
        $this->images->attach($product, $files, 'products', $this->videos->lastMediaOrder($product));

        return array_values(array_diff($product->images()->reorder()->orderBy('sort_order')->orderBy('id')->pluck('id')->all(), $before));
    }

    /** Remove gallery images AND their files. @param int[] $ids */
    public function removeImages(Product $product, array $ids): void
    {
        $this->images->removeByIds($product, $ids);
    }

    /**
     * Remove gallery images but KEEP their files, so restoreImages() can put them back (marketing suggestion rollback).
     *
     * @param  int[]  $ids  @return list<array{path:string, thumb_path:?string, sort_order:int}>
     */
    public function detachImages(Product $product, array $ids): array
    {
        $rows = $product->images()->whereIn('id', $ids)->get();
        $product->images()->whereIn('id', $rows->pluck('id'))->delete();

        return $rows->map(fn ($i) => ['path' => $i->path, 'thumb_path' => $i->thumb_path, 'sort_order' => (int) $i->sort_order])->values()->all();
    }

    /** Re-attach images removed by detachImages() (rows whose file path is already in the gallery are skipped). */
    public function restoreImages(Product $product, array $rows): void
    {
        $have = $product->images()->pluck('path')->all();
        foreach ($rows as $r) {
            if (! in_array($r['path'] ?? null, $have, true)) {
                $product->images()->create(['path' => $r['path'], 'thumb_path' => $r['thumb_path'] ?? null, 'sort_order' => (int) ($r['sort_order'] ?? 0)]);
            }
        }
    }

    /**
     * Replace a variant's image with $file (a variant shows one image). The old rows are detached but their FILES are kept, so
     * restoreVariantImage() can undo it. @return array{added:int[],removed:array<int,array{path:string,thumb_path:?string,sort_order:int}>}
     */
    public function replaceVariantImage(ProductVariant $variant, UploadedFile $file): array
    {
        $old = $variant->images()->get();
        $this->images->attach($variant, [$file], 'variants');
        $variant->images()->whereIn('id', $old->pluck('id'))->delete();

        return ['added' => $variant->images()->pluck('id')->map(fn ($v) => (int) $v)->values()->all(),
            'removed' => $old->map(fn ($i) => ['path' => $i->path, 'thumb_path' => $i->thumb_path, 'sort_order' => (int) $i->sort_order])->values()->all()];
    }

    /** Undo replaceVariantImage(): delete the added images (and files), re-attach the removed rows. */
    public function restoreVariantImage(ProductVariant $variant, array $added, array $removed): void
    {
        $added && $this->images->removeByIds($variant, $added);
        $have = $variant->images()->pluck('path')->all();
        foreach ($removed as $r) {
            if (! in_array($r['path'] ?? null, $have, true)) {
                $variant->images()->create(['path' => $r['path'], 'thumb_path' => $r['thumb_path'] ?? null, 'sort_order' => (int) ($r['sort_order'] ?? 0)]);
            }
        }
    }

    /** Make one gallery image the main (first) one: it takes the lowest position, the other images and videos (same order) move one down. */
    public function setMainImage(Product $product, int $imageId): void
    {
        $mins = array_filter([$product->images()->reorder()->min('sort_order'), $product->videos()->reorder()->min('sort_order')], fn ($v) => $v !== null);
        $min = $mins ? (int) min($mins) : 0;   // sort_order is unsigned: shift the others instead of going below 0
        DB::transaction(function () use ($product, $imageId, $min) {
            $product->images()->reorder()->whereKeyNot($imageId)->where('sort_order', '>=', $min)->increment('sort_order');
            $product->videos()->reorder()->where('sort_order', '>=', $min)->increment('sort_order');
            $product->images()->reorder()->whereKey($imageId)->update(['sort_order' => $min]);
        });
    }

    /** @return array{images: array<int, int>, videos: array<int, int>} current gallery order (id => sort_order), for restoreMediaOrder() */
    public function mediaOrder(Product $product): array
    {
        $map = fn ($q) => $q->reorder()->get(['id', 'sort_order'])->mapWithKeys(fn ($m) => [$m->id => (int) $m->sort_order])->all();

        return ['images' => $map($product->images()), 'videos' => $map($product->videos())];
    }

    /** Put the gallery order back (ids that no longer exist are ignored). */
    public function restoreMediaOrder(Product $product, array $order): void
    {
        foreach ((array) ($order['images'] ?? []) as $id => $o) {
            $product->images()->reorder()->whereKey((int) $id)->update(['sort_order' => (int) $o]);
        }
        foreach ((array) ($order['videos'] ?? []) as $id => $o) {
            $product->videos()->reorder()->whereKey((int) $id)->update(['sort_order' => (int) $o]);
        }
    }

    private function finish(Product $product, ProductData $dto): Product
    {
        $this->products->syncCategories($product, $dto->categoryIds);
        $this->products->syncTags($product, $dto->tagIds);
        $this->images->removeByIds($product, $dto->removeImageIds);
        $this->images->attach($product, $dto->images, 'products', $this->videos->lastMediaOrder($product));
        $this->syncVariants($product, $dto);

        return $product->refresh();
    }

    private function payload(ProductData $dto, ?Product $product = null): array
    {
        $draft = $dto->isDraft ?? (bool) $product?->is_draft;
        $barcode = $dto->type === 'variable' ? null : ($dto->barcode ?: $product?->barcode ?: $this->barcodes->generateUnique());

        return [
            'type' => $dto->type,
            'brand_id' => $dto->brandId,
            'name' => $dto->name,
            'description' => $dto->description,
            'meta_title' => $dto->metaTitle,
            'meta_description' => $dto->metaDescription,
            'slug' => Slug::unique(Product::class, $dto->slug ?: $product?->slug, $dto->name, $product?->id),
            'sku' => $dto->sku ?? $product?->sku ?? self::draftSku(), // only a draft may leave it blank (placeholder until published)
            'is_draft' => $draft,
            'barcode' => $barcode,
            'weight' => $dto->weight,
            'is_active' => ! $draft && $dto->isActive,
            'price' => $dto->price,
            'sale_price' => $dto->price === null ? null : $dto->salePrice,
            'cost' => $dto->cost ?? $product?->cost ?? '0', // products.cost is NOT NULL (default 0): a blank field keeps the current cost
            'min_stock_alert_qty' => $dto->minStockAlert,
            'gift_suitable' => $dto->giftSuitable,
            'gift_recipients' => $dto->giftRecipients ?: null,
            'gift_occasions' => $dto->giftOccasions ?: null,
        ];
    }

    /** Variable: upsert by combination (restoring soft-deleted ones), remove the rest. Simple: drop all variants. */
    private function syncVariants(Product $product, ProductData $dto): void
    {
        $existing = $this->products->variantsWithTrashed($product);
        $byHash = $existing->keyBy('combination_hash');
        $keep = [];

        foreach ($dto->variants as $row) {
            $hash = ProductVariant::hashFor($row->valueIds);
            $variant = ($row->id ? $existing->get($row->id) : null) ?? $byHash->get($hash);
            $data = ['combination_hash' => $hash, 'sku' => $row->sku ?? $variant?->sku ?? self::draftSku(), 'weight' => $row->weight, 'is_active' => $row->isActive,
                'price' => $row->price, 'sale_price' => $row->salePrice, 'cost' => $row->cost, 'min_stock_alert_qty' => $row->minStockAlert];

            if ($variant) {
                $variant->trashed() && $variant->restore();
                $variant->update($data);
            } else {
                $variant = $product->variants()->create($data + ['barcode' => $this->barcodes->generateUnique()]);
            }
            $variant->values()->sync($row->valueIds);
            $this->attachVariantImage($variant, $row);
            $keep[] = $variant->id;
        }

        $existing->reject(fn ($v) => in_array($v->id, $keep) || $v->trashed())->each->delete();
    }

    /** Placeholder SKU for a draft saved without one; the product form shows it blank and publishing requires a real one. */
    public static function draftSku(): string
    {
        return self::DRAFT_SKU_PREFIX.Str::upper(Str::random(10));
    }

    public static function isDraftSku(?string $sku): bool
    {
        return $sku !== null && str_starts_with($sku, self::DRAFT_SKU_PREFIX);
    }

    private function attachVariantImage(ProductVariant $variant, ProductVariantData $row): void
    {
        if ($row->image) {
            $old = $variant->images()->pluck('id')->all();
            $this->images->attach($variant, [$row->image], 'variants'); // new first, then drop the replaced files
            $this->images->removeByIds($variant, $old);
        }
    }
}
