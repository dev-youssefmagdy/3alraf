<?php

namespace App\Repositories\Eloquent;

use App\Models\Language;
use App\Repositories\Contracts\LanguageRepositoryInterface;

class LanguageRepository extends BaseRepository implements LanguageRepositoryInterface
{
    protected string $model = Language::class;

    public function clearDefault(?int $exceptId = null): void
    {
        $this->query()->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->update(['is_default' => false]);
    }

    public function defaultLanguage(): ?Language
    {
        return $this->query()->where('is_default', true)->first();
    }
}
