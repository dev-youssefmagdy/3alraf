<?php

namespace App\Repositories\Contracts;

interface AttributeRepositoryInterface extends RepositoryInterface
{
    /** Attributes with values, for pickers. */
    public function withValues(): \Illuminate\Database\Eloquent\Collection;
}
