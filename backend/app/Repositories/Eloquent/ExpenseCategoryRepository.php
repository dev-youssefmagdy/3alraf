<?php

namespace App\Repositories\Eloquent;

use App\Models\ExpenseCategory;
use App\Repositories\Contracts\ExpenseCategoryRepositoryInterface;

class ExpenseCategoryRepository extends BaseRepository implements ExpenseCategoryRepositoryInterface
{
    protected string $model = ExpenseCategory::class;
}
