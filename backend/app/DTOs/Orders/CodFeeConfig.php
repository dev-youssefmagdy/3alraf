<?php

namespace App\DTOs\Orders;

use App\Http\Requests\Tenant\CodFeeSettingsRequest;

/**
 * Per-tenant COD extra fee settings (tenants.settings.cod_extra_fee_*), see docs/CALCULATIONS.md "COD extra fee".
 * Used by the settings form (DTO), the quote (CalculatorInput) and the order snapshot.
 */
final readonly class CodFeeConfig
{
    public const TYPES = ['fixed', 'percent', 'fixed_plus_percent'];

    /**
     * @param  array<int,string>  $cityFixed  city_id => flat amount
     * @param  array<string,string>  $label  locale => text
     */
    public function __construct(
        public bool $enabled = false,
        public string $type = 'fixed',
        public string $fixed = '0',
        public string $percent = '0',
        public ?string $min = null,
        public ?string $max = null,
        public ?string $freeOver = null,
        public string $bearer = 'customer',
        public array $cityFixed = [],
        public array $label = [],
    ) {}

    public static function fromSettings(?array $s): self
    {
        $s ??= [];
        $n = fn ($v) => ($v === null || $v === '') ? null : (string) $v;
        $type = in_array($s['cod_extra_fee_type'] ?? 'fixed', self::TYPES, true) ? ($s['cod_extra_fee_type'] ?? 'fixed') : 'fixed';

        return new self(
            (bool) ($s['cod_extra_fee_enabled'] ?? false), $type, $n($s['cod_extra_fee_fixed'] ?? null) ?? '0', $n($s['cod_extra_fee_percent'] ?? null) ?? '0',
            $n($s['cod_extra_fee_min'] ?? null), $n($s['cod_extra_fee_max'] ?? null), $n($s['cod_extra_fee_free_over'] ?? null),
            ($s['cod_extra_fee_bearer'] ?? 'customer') === 'merchant' ? 'merchant' : 'customer',
            collect($s['cod_extra_fee_city_fixed'] ?? [])->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (string) $v)->all(),
            (array) ($s['cod_extra_fee_label'] ?? []),
        );
    }

    public static function fromRequest(CodFeeSettingsRequest $r): self
    {
        return new self(
            $r->boolean('cod_extra_fee_enabled'), (string) $r->input('cod_extra_fee_type', 'fixed'), (string) ($r->input('cod_extra_fee_fixed') ?: '0'),
            (string) ($r->input('cod_extra_fee_percent') ?: '0'), self::nullable($r->input('cod_extra_fee_min')), self::nullable($r->input('cod_extra_fee_max')),
            self::nullable($r->input('cod_extra_fee_free_over')), (string) $r->input('cod_extra_fee_bearer', 'customer'),
            collect($r->input('cod_extra_fee_city_fixed', []))->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (string) $v)->all(),
            $r->cleanTranslations($r->input('cod_extra_fee_label')),
        );
    }

    /** @return array<string,mixed> the keys merged into tenants.settings */
    public function toSettings(): array
    {
        return ['cod_extra_fee_enabled' => $this->enabled, 'cod_extra_fee_type' => $this->type, 'cod_extra_fee_fixed' => $this->fixed,
            'cod_extra_fee_percent' => $this->percent, 'cod_extra_fee_min' => $this->min, 'cod_extra_fee_max' => $this->max,
            'cod_extra_fee_free_over' => $this->freeOver, 'cod_extra_fee_bearer' => $this->bearer, 'cod_extra_fee_city_fixed' => $this->cityFixed,
            'cod_extra_fee_label' => $this->label];
    }

    /** Label translations with the built-in default when the tenant set none. */
    public function labels(): array
    {
        return $this->label ?: ['ar' => __('orders.cod_fee_default', [], 'ar'), 'en' => __('orders.cod_fee_default', [], 'en')];
    }

    public function labelFor(?string $locale = null): string
    {
        $l = $this->labels();

        return $l[$locale ?? app()->getLocale()] ?? $l[config('app.fallback_locale')] ?? reset($l);
    }

    private static function nullable(mixed $v): ?string
    {
        return ($v === null || $v === '') ? null : (string) $v;
    }
}
