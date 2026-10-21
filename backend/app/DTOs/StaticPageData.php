<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\StaticPageRequest;

final readonly class StaticPageData
{
    public function __construct(public string $slug, public array $title, public array $content, public bool $isActive) {}

    public static function fromRequest(StaticPageRequest $r): self
    {
        return new self(strtolower(trim($r->input('slug'))), $r->cleanTranslations($r->input('title')), $r->cleanTranslations($r->input('content')), $r->boolean('is_active'));
    }

    public function toArray(): array
    {
        return ['slug' => $this->slug, 'title' => $this->title, 'content' => $this->content, 'is_active' => $this->isActive];
    }
}
