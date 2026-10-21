<?php

namespace App\Repositories\Eloquent;

use App\Models\Attribute;
use App\Repositories\Contracts\AttributeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AttributeRepository extends BaseRepository implements AttributeRepositoryInterface
{
    protected string $model = Attribute::class;

    public function withValues(): Collection
    {
        return $this->query()->with('values')->orderBy('sort_order')->orderBy('id')->get();
    }
}
