<?php

namespace App\Http\Requests\Concerns;

use App\Models\Language;

trait TranslatableRules
{
    /** Rules for `field` and `field.{locale}`: default language required (when $required), others optional. */
    protected function translatable(string $field, bool $required = true, int $max = 255): array
    {
        $langs = Language::activeList();
        $default = $langs->firstWhere('is_default', true)?->code ?? $langs->first()?->code;
        $rules = [$field => [$required ? 'required' : 'nullable', 'array']];
        foreach ($langs as $l) {
            $rules["$field.{$l->code}"] = [$required && $l->code === $default ? 'required' : 'nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /** Keep only active locales, drop empty strings. */
    public function cleanTranslations(?array $values): array
    {
        $codes = Language::activeList()->pluck('code')->all();

        return collect($values ?? [])->only($codes)->filter(fn ($v) => $v !== null && trim((string) $v) !== '')->all();
    }
}
