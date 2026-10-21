<?php

namespace App\Services;

use App\DTOs\LanguageData;
use App\Models\Language;
use App\Repositories\Contracts\LanguageRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LanguageService
{
    public function __construct(private LanguageRepositoryInterface $languages) {}

    public function create(LanguageData $dto): Language
    {
        return DB::transaction(function () use ($dto) {
            $lang = $this->languages->create($dto->toArray());
            if ($lang->is_default) {
                $this->languages->clearDefault($lang->id);
            }

            return $lang;
        });
    }

    public function update(Language $language, LanguageData $dto): Language
    {
        if ($language->is_default && ! $dto->isDefault) {
            throw ValidationException::withMessages(['is_default' => __('admin.languages_need_default')]);
        }

        return DB::transaction(function () use ($language, $dto) {
            $lang = $this->languages->update($language, $dto->toArray());
            if ($lang->is_default) {
                $this->languages->clearDefault($lang->id);
            }

            return $lang;
        });
    }

    public function delete(Language $language): void
    {
        if ($language->is_default) {
            throw ValidationException::withMessages(['language' => __('admin.languages_cannot_delete_default')]);
        }
        $this->languages->delete($language);
    }
}
