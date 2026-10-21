<?php

namespace App\Services;

use App\DTOs\CategoryData;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(private CategoryRepositoryInterface $categories, private ImageUploadService $images) {}

    public function create(CategoryData $dto): Category
    {
        return DB::transaction(function () use ($dto) {
            $cat = $this->categories->create($this->payload($dto) + ['path' => '', 'depth' => 0]);
            $this->place($cat, $dto->parentId);

            return $cat->refresh();
        });
    }

    public function update(Category $category, CategoryData $dto): Category
    {
        return DB::transaction(function () use ($category, $dto) {
            $oldPath = $category->path;
            $cat = $this->categories->update($category, $this->payload($dto, $category));
            if ($cat->parent_id !== $dto->parentId || $cat->path === '') {
                $this->place($cat, $dto->parentId);
                $this->rebaseDescendants($cat, $oldPath);
            }

            return $cat->refresh();
        });
    }

    public function delete(Category $category): void
    {
        if ($this->categories->hasChildren($category)) {
            throw ValidationException::withMessages(['category' => __('admin.category_has_children')]);
        }
        $this->images->deletePath($category->image);
        $this->categories->delete($category);
    }

    /** Sets parent_id/path/depth from the parent. */
    private function place(Category $cat, ?int $parentId): void
    {
        $parent = $parentId ? $this->categories->findOrFail($parentId) : null;
        $cat->forceFill([
            'parent_id' => $parent?->id,
            'path' => ($parent ? $parent->path.'/' : '').$cat->id,
            'depth' => $parent ? $parent->depth + 1 : 0,
        ])->save();
    }

    private function rebaseDescendants(Category $cat, string $oldPath): void
    {
        if ($oldPath === '' || $oldPath === $cat->path) {
            return;
        }
        $delta = $cat->depth - (substr_count($oldPath, '/'));
        $this->categories->query()->where('path', 'like', $oldPath.'/%')->get()->each(function (Category $d) use ($oldPath, $cat, $delta) {
            $d->forceFill(['path' => $cat->path.substr($d->path, strlen($oldPath)), 'depth' => $d->depth + $delta])->save();
        });
    }

    private function payload(CategoryData $dto, ?Category $cat = null): array
    {
        $data = [
            'name' => $dto->name,
            'description' => $dto->description,
            'slug' => Slug::unique(Category::class, $dto->slug ?: $cat?->slug, $dto->name, $cat?->id),
            'icon' => $dto->icon,
            'sort_order' => $dto->sortOrder,
            'is_active' => $dto->isActive,
        ];
        if ($dto->image) {
            $data['image'] = $this->images->storeSingle($dto->image, 'categories', $cat?->image);
        } elseif ($dto->removeImage && $cat?->image) {
            $this->images->deletePath($cat->image);
            $data['image'] = null;
        }

        return $data;
    }
}
