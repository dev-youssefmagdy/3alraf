<?php

namespace App\Services;

use App\DTOs\ProductData;
use App\DTOs\ProductVariantData;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Qr\QrCodeManager;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ImageUploadService $images,
        private BarcodeService $barcodes,
        private QrCodeManager $qr,
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
            $this->qr->queueCatalogProduct($product->id); // slug / variants / status may have changed: refresh every tenant's codes

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

    private function finish(Product $product, ProductData $dto): Product
    {
        $this->products->syncCategories($product, $dto->categoryIds);
        $this->products->syncTags($product, $dto->tagIds);
        $this->images->removeByIds($product, $dto->removeImageIds);
        $this->images->attach($product, $dto->images, 'products');
        $this->syncVariants($product, $dto);

        return $product->refresh();
    }

    private function payload(ProductData $dto, ?Product $product = null): array
    {
        $barcode = $dto->type === 'variable' ? null : ($dto->barcode ?: $product?->barcode ?: $this->barcodes->generateUnique());

        return [
            'type' => $dto->type,
            'brand_id' => $dto->brandId,
            'name' => $dto->name,
            'description' => $dto->description,
            'meta_title' => $dto->metaTitle,
            'meta_description' => $dto->metaDescription,
            'slug' => Slug::unique(Product::class, $dto->slug ?: $product?->slug, $dto->name, $product?->id),
            'sku' => $dto->sku,
            'barcode' => $barcode,
            'weight' => $dto->weight,
            'is_active' => $dto->isActive,
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
            $data = ['combination_hash' => $hash, 'sku' => $row->sku, 'weight' => $row->weight, 'is_active' => $row->isActive];

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

    private function attachVariantImage(ProductVariant $variant, ProductVariantData $row): void
    {
        if ($row->image) {
            $old = $variant->images()->pluck('id')->all();
            $this->images->attach($variant, [$row->image], 'variants'); // new first, then drop the replaced files
            $this->images->removeByIds($variant, $old);
        }
    }
}
