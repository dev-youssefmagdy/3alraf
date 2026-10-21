<?php

namespace App\Services;

use App\Repositories\Contracts\DiscountCodeRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class DiscountCodeService extends CrudService
{
    public function __construct(DiscountCodeRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }

    protected function guardDelete(Model $model): void
    {
        if ($model->usages()->exists()) {
            $this->block('orders.discount.in_use');
        }
    }
}
