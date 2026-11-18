<?php

namespace App\DataTables;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/** One control of a table's filter bar: rendered by <x-datatable>, applied to the query by BaseDataTable::ajax(). */
final class Filter
{
    private const MAX_TEXT = 100;

    private function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type,
        private readonly Closure $apply,
        private readonly array|Closure $options = [],
        public readonly bool $searchable = false,
    ) {}

    /** $apply = fn (Builder $q, string $value) */
    public static function text(string $name, string $label, Closure $apply): self
    {
        return new self($name, $label, 'text', $apply);
    }

    public static function date(string $name, string $label, Closure $apply): self
    {
        return new self($name, $label, 'date', $apply);
    }

    /** $options = value => label, or a closure returning them (only runs when the bar renders or this filter is used). */
    public static function select(string $name, string $label, array|Closure $options, Closure $apply, bool $searchable = false): self
    {
        return new self($name, $label, 'select', $apply, $options, $searchable);
    }

    public function options(): array
    {
        return $this->options instanceof Closure ? ($this->options)() : $this->options;
    }

    /** Ignores anything the control could not have sent. */
    public function apply(Builder $query, mixed $value): void
    {
        if (! is_string($value) || ($value = trim($value)) === '' || ! $this->accepts($value)) {
            return;
        }
        ($this->apply)($query, mb_substr($value, 0, self::MAX_TEXT));
    }

    private function accepts(string $value): bool
    {
        return match ($this->type) {
            'select' => array_key_exists($value, $this->options()),
            'date' => preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]),
            default => true,
        };
    }
}
