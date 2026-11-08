<?php

namespace App\Services\Marketing\Suggestions\Types;

use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Services\Marketing\Suggestions\Contracts\SuggestionType;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Support\Marketing\RichText;
use App\Support\Marketing\StoreText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** Shared helpers of the type handlers (translatable rules, normalisers, defaults). */
abstract class AbstractSuggestionType implements SuggestionType
{
    /** target_entity_type => model class */
    protected array $targetModels = [];

    public function targetTypes(): array
    {
        return array_keys($this->targetModels);
    }

    public function targetRequired(?array $payload = null): bool
    {
        return false;
    }

    public function resolveTarget(?string $type, ?int $id): ?Model
    {
        if (! $type || ! $id || ! isset($this->targetModels[$type])) {
            return null;
        }

        return $this->targetModels[$type]::query()->find($id);   // soft-deleted rows are excluded by the global scope
    }

    public function supportsRollback(): bool
    {
        return false;
    }

    public function rollback(MarketingSuggestion $s, MarketingSuggestionExecution $exec, ?int $adminId): array
    {
        throw SuggestionException::make('rollback_not_supported', 422);
    }

    public function approvePermission(): ?string
    {
        return null;
    }

    public function applicable(): bool
    {
        return true;
    }

    public function applyOptions(): array
    {
        return [];
    }

    // ------------------------------------------------------------------ helpers

    protected function locales(): array
    {
        return StoreText::locales();
    }

    /** Rules for a {locale: string} field: payload.{path} array + one rule per locale. */
    protected function localeRules(string $path, int $max, bool $required = false): array
    {
        $r = ['payload.'.$path => [$required ? 'required' : 'nullable', 'array']];
        foreach ($this->locales() as $l) {
            $r['payload.'.$path.'.'.$l] = ['nullable', 'string', 'max:'.$max];
        }

        return $r;
    }

    /** Trimmed plain strings per known locale, empty ones dropped (control chars removed, tags stripped). */
    protected function plainLocales(mixed $v): array
    {
        $out = [];
        foreach ($this->locales() as $l) {
            $s = is_array($v) ? ($v[$l] ?? null) : null;
            if (is_string($s) && ($s = trim((string) StoreText::plain($s))) !== '') {
                $out[$l] = $s;
            }
        }

        return $out;
    }

    /** Sanitised rich text per locale (RichText allowlist). */
    protected function htmlLocales(mixed $v): array
    {
        $out = [];
        foreach ($this->locales() as $l) {
            $s = is_array($v) ? ($v[$l] ?? null) : null;
            if (is_string($s) && ($s = RichText::clean($s)) !== '') {
                $out[$l] = $s;
            }
        }

        return $out;
    }

    protected function plain(mixed $v, int $max = 5000): ?string
    {
        $s = is_scalar($v) ? trim((string) StoreText::plain((string) $v)) : '';

        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    protected function dt(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse((string) $v)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function intList(mixed $v): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $v), fn ($i) => $i > 0)));
        sort($ids);

        return $ids;
    }

    protected function money(mixed $v): ?string
    {
        return ($v === null || $v === '') ? null : number_format(round((float) $v, 2), 2, '.', '');
    }

    protected function label(string $key): string
    {
        return __('marketing.suggestions.fields.'.$key);
    }

    /** Translations of a model attribute (raw, no fallback). */
    protected function translations(?Model $m, string $attr): array
    {
        return $m && method_exists($m, 'getTranslations') ? array_filter($m->getTranslations($attr), fn ($v) => $v !== null && $v !== '') : [];
    }

    /** Throw the check() errors (apply-time re-validation). */
    protected function ensure(array $check): void
    {
        if ($check['errors'] ?? []) {
            throw SuggestionException::validation($check['errors']);
        }
    }

    /** Compare row. */
    protected function row(string $field, mixed $current, mixed $proposed, ?string $label = null): array
    {
        return ['field' => $field, 'label' => $label ?? $this->label($field), 'current' => $current, 'proposed' => $proposed];
    }

    /** Edit-form field definition. */
    protected function field(string $name, string $kind, ?string $label = null, array $extra = []): array
    {
        return ['name' => $name, 'kind' => $kind, 'label' => $label ?? $this->label(\Illuminate\Support\Str::afterLast($name, '.'))] + $extra;
    }
}
