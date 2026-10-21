<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Support\Collection;

interface CategoryRepositoryInterface extends RepositoryInterface
{
    /** Flat list ordered depth-first (sort_order within each level), each with ->depth. */
    public function flatTree(?int $excludeBranchOfId = null): Collection;

    public function descendants(Category $category): Collection;

    public function hasChildren(Category $category): bool;
}
