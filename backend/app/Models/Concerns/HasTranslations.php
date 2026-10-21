<?php

namespace App\Models\Concerns;

/**
 * JSON-column translations keyed by locale. Declare `protected array $translatable = ['name', ...]`
 * (columns must be json). Reading the attribute returns the current-locale string (fallback chain);
 * assigning an array sets all locales, assigning a string sets the current locale.
 */
trait HasTranslations
{
    public function getTranslatableAttributes(): array
    {
        return $this->translatable ?? [];
    }

    public function isTranslatable(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes(), true);
    }

    public function getAttribute($key)
    {
        if ($this->isTranslatable($key)) {
            return $this->getTranslation($key, app()->getLocale());
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if ($this->isTranslatable($key)) {
            if (is_array($value)) {
                return $this->setTranslations($key, $value);
            }

            return $this->setTranslation($key, app()->getLocale(), $value);
        }

        return parent::setAttribute($key, $value);
    }

    public function getTranslations(string $key): array
    {
        $raw = $this->attributes[$key] ?? null;
        $v = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($v) ? $v : [];
    }

    public function getTranslation(string $key, ?string $locale = null, bool $fallback = true): ?string
    {
        $t = $this->getTranslations($key);
        $locale ??= app()->getLocale();
        if (($t[$locale] ?? '') !== '') {
            return $t[$locale];
        }
        if (! $fallback) {
            return null;
        }
        foreach ([config('app.fallback_locale'), function_exists('current_tenant') ? current_tenant()?->default_locale : null] as $l) {
            if ($l && ($t[$l] ?? '') !== '') {
                return $t[$l];
            }
        }

        return collect($t)->first(fn ($x) => $x !== null && $x !== '');
    }

    public function setTranslation(string $key, string $locale, ?string $value): static
    {
        $t = $this->getTranslations($key);
        $t[$locale] = $value;
        $this->attributes[$key] = json_encode($t, JSON_UNESCAPED_UNICODE);

        return $this;
    }

    public function setTranslations(string $key, array $values): static
    {
        $this->attributes[$key] = json_encode(array_filter($values, fn ($v) => $v !== null && $v !== ''), JSON_UNESCAPED_UNICODE);

        return $this;
    }

    /** Eloquent where on a translated column in the given locale. */
    public function scopeWhereTranslation($q, string $key, string $value, ?string $locale = null)
    {
        return $q->where($key.'->'.($locale ?? app()->getLocale()), $value);
    }
}
