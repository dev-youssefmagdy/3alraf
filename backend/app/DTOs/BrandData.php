<?php

namespace App\DTOs;

use App\Http\Requests\Admin\BrandRequest;
use Illuminate\Http\UploadedFile;

final readonly class BrandData
{
    public function __construct(
        public array $name,
        public ?string $slug,
        public bool $isActive,
        public int $sortOrder,
        public ?UploadedFile $logo,
        public bool $removeLogo,
        public ?bool $isDraft = null, // null = keep the record's current draft state
    ) {}

    public static function fromRequest(BrandRequest $r): self
    {
        $v = $r->validated();

        return new self($r->cleanName(), $v['slug'] ?? null, $r->boolean('is_active'), (int) ($v['sort_order'] ?? 0), $r->file('logo'), $r->boolean('remove_logo'), $r->isDraft());
    }
}
