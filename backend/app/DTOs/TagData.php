<?php

namespace App\DTOs;

use App\Http\Requests\Central\TagRequest;

final readonly class TagData
{
    public function __construct(public array $name, public ?string $slug, public bool $isActive, public int $sortOrder) {}

    public static function fromRequest(TagRequest $r): self
    {
        $v = $r->validated();

        return new self($r->cleanTranslations($r->input('name')), $v['slug'] ?? null, $r->boolean('is_active'), (int) ($v['sort_order'] ?? 0));
    }
}
