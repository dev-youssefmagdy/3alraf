<?php

namespace App\Services;

use App\Repositories\Contracts\ExpenseCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategoryService extends CrudService
{
    public function __construct(ExpenseCategoryRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }

    protected function guardDelete(Model $model): void
    {
        if ($model->expenses()->exists()) {
            $this->block('ops.expense_category_in_use');
        }
    }
}
