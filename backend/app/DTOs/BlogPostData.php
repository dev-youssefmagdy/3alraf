<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\BlogPostRequest;

final readonly class BlogPostData
{
    public function __construct(public string $slug, public array $title, public array $excerpt, public array $content, public array $metaDescription, public bool $isActive, public ?string $publishedAt) {}

    public static function fromRequest(BlogPostRequest $r): self
    {
        return new self(strtolower(trim($r->input('slug'))), $r->cleanTranslations($r->input('title')), $r->cleanTranslations($r->input('excerpt')), $r->cleanTranslations($r->input('content')),
            $r->cleanTranslations($r->input('meta_description')), $r->boolean('is_active'), $r->filled('published_at') ? $r->input('published_at') : null);
    }

    public function toArray(): array
    {
        return ['slug' => $this->slug, 'title' => $this->title, 'excerpt' => $this->excerpt, 'content' => $this->content, 'meta_description' => $this->metaDescription,
            'is_active' => $this->isActive, 'published_at' => $this->publishedAt ?? ($this->isActive ? now() : null)];
    }
}
