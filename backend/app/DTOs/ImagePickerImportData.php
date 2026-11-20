<?php

namespace App\DTOs;

use App\Http\Requests\Admin\ImagePickerImportRequest;

/** Import one web picture the admin chose: into the product gallery, as the product's main photo, or as a variant's photo. */
final readonly class ImagePickerImportData
{
    public function __construct(
        public string $imageUrl,
        public ?string $pageUrl,
        public ?string $title,
        public string $as,             // gallery | main
        public ?int $variantId = null, // main + variant = the variant's photo
    ) {}

    public static function fromRequest(ImagePickerImportRequest $r): self
    {
        $v = $r->validated();

        return new self($v['image_url'], $v['page_url'] ?? null, $v['title'] ?? null, $v['as'], isset($v['variant_id']) ? (int) $v['variant_id'] : null);
    }
}
