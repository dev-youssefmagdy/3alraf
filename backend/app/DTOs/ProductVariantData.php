<?php

namespace App\DTOs;

use Illuminate\Http\UploadedFile;

final readonly class ProductVariantData
{
    /** @param int[] $valueIds */
    public function __construct(
        public ?int $id,
        public array $valueIds,
        public string $sku,
        public ?string $weight,
        public bool $isActive,
        public ?UploadedFile $image,
        public ?string $price = null,
        public ?string $salePrice = null,
        public ?string $cost = null,
        public ?int $minStockAlert = null,
    ) {}
}
