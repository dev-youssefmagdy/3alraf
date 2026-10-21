<?php

namespace App\DTOs;

use App\Http\Requests\Central\CategoryRequest;
use Illuminate\Http\UploadedFile;

final readonly class CategoryData
{
    public function __construct(
        public array $name,
        public array $description,
        public ?int $parentId,
        public ?string $slug,
        public ?string $icon,
        public int $sortOrder,
        public bool $isActive,
        public ?UploadedFile $image,
        public bool $removeImage,
    ) {}

    public static function fromRequest(CategoryRequest $r): self
    {
        $v = $r->validated();

        return new self($r->cleanTranslations($v['name']), $r->cleanTranslations($v['description'] ?? []),
            ! empty($v['parent_id']) ? (int) $v['parent_id'] : null, $v['slug'] ?? null, $v['icon'] ?? null,
            (int) ($v['sort_order'] ?? 0), $r->boolean('is_active'), $r->file('image'), $r->boolean('remove_image'));
    }
}
