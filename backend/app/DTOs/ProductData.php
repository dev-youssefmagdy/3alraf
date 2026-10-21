<?php

namespace App\DTOs;

use App\Http\Requests\Central\ProductRequest;
use Illuminate\Http\UploadedFile;

final readonly class ProductData
{
    /**
     * @param  int[]  $categoryIds
     * @param  UploadedFile[]  $images
     * @param  int[]  $removeImageIds
     * @param  ProductVariantData[]  $variants
     */
    public function __construct(
        public string $type,
        public array $name,
        public array $description,
        public array $metaTitle,
        public array $metaDescription,
        public ?string $slug,
        public string $sku,
        public ?string $barcode,
        public ?string $weight,
        public ?int $brandId,
        public array $categoryIds,
        public bool $isActive,
        public array $images,
        public array $removeImageIds,
        public array $variants,
        public array $tagIds = [],
    ) {}

    public static function fromRequest(ProductRequest $r): self
    {
        $v = $r->validated();
        $variable = $v['type'] === 'variable';
        $variants = [];
        if ($variable) {
            foreach ($r->input('variants', []) as $key => $row) {
                $variants[] = new ProductVariantData(
                    ! empty($row['id']) ? (int) $row['id'] : null,
                    array_map('intval', $row['values'] ?? []),
                    $row['sku'],
                    isset($row['weight']) && $row['weight'] !== '' ? (string) $row['weight'] : null,
                    filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                    $r->file("variants.$key.image"),
                );
            }
        }

        return new self($v['type'], $r->cleanTranslations($v['name']), $r->cleanTranslations($v['description'] ?? []),
            $r->cleanTranslations($v['meta_title'] ?? []), $r->cleanTranslations($v['meta_description'] ?? []),
            $v['slug'] ?? null, $v['sku'], $variable ? null : ($v['barcode'] ?? null),
            isset($v['weight']) ? (string) $v['weight'] : null, ! empty($v['brand_id']) ? (int) $v['brand_id'] : null,
            array_map('intval', $v['category_ids']), $r->boolean('is_active'),
            $r->file('images', []), array_map('intval', $r->input('remove_images', [])), $variants,
            array_map('intval', $v['tag_ids'] ?? []));
    }
}
