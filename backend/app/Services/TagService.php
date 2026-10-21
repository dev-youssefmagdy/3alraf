<?php

namespace App\Services;

use App\DTOs\TagData;
use App\Models\Tag;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Support\Slug;

class TagService
{
    public function __construct(private TagRepositoryInterface $tags) {}

    public function create(TagData $dto): Tag
    {
        return $this->tags->create($this->payload($dto));
    }

    public function update(Tag $tag, TagData $dto): Tag
    {
        return $this->tags->update($tag, $this->payload($dto, $tag));
    }

    /** Tags are labels only: deleting detaches them from products. */
    public function delete(Tag $tag): void
    {
        $tag->products()->detach();
        $this->tags->delete($tag);
    }

    private function payload(TagData $dto, ?Tag $tag = null): array
    {
        return [
            'name' => $dto->name,
            'slug' => Slug::unique(Tag::class, $dto->slug ?: $tag?->slug, $dto->name, $tag?->id),
            'is_active' => $dto->isActive,
            'sort_order' => $dto->sortOrder,
        ];
    }
}
