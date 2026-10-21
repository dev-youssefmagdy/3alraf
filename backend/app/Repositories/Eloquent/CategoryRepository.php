<?php

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Support\Collection;

class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    protected string $model = Category::class;

    public function flatTree(?int $excludeBranchOfId = null): Collection
    {
        $all = $this->query()->withCount('products')->orderBy('sort_order')->orderBy('id')->get()->groupBy(fn ($c) => $c->parent_id ?? 0);
        $out = collect();
        $walk = function (int $parent) use (&$walk, $all, $out, $excludeBranchOfId) {
            foreach ($all->get($parent, []) as $c) {
                if ($c->id === $excludeBranchOfId) {
                    continue;
                }
                $out->push($c);
                $walk($c->id);
            }
        };
        $walk(0);

        return $out;
    }

    public function descendants(Category $category): Collection
    {
        return $this->query()->where('path', 'like', $category->path.'/%')->get();
    }

    public function hasChildren(Category $category): bool
    {
        return $this->query()->where('parent_id', $category->id)->exists();
    }
}
