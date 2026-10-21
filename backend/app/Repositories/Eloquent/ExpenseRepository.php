<?php

namespace App\Repositories\Eloquent;

use App\Models\Expense;
use App\Repositories\Contracts\ExpenseRepositoryInterface;

class ExpenseRepository extends BaseRepository implements ExpenseRepositoryInterface
{
    protected string $model = Expense::class;
}
