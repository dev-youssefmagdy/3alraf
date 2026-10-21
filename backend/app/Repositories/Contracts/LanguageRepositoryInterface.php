<?php

namespace App\Repositories\Contracts;

interface LanguageRepositoryInterface extends RepositoryInterface
{
    public function clearDefault(?int $exceptId = null): void;

    public function defaultLanguage(): ?\App\Models\Language;
}
