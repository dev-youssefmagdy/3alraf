<?php

namespace App\DTOs;

use App\Http\Requests\Central\TenantRequest;

final readonly class TenantData
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $country,
        public string $currencyCode,
        public string $currencySymbol,
        public string $currencyRate,
        public string $defaultLocale,
        public array $locales,
        public string $timezone,
        public bool $isActive,
        public ?string $adminName = null,
        public ?string $adminPhone = null,
        public ?string $adminPassword = null,
    ) {}

    public static function fromRequest(TenantRequest $r): self
    {
        $v = $r->validated();
        $locales = array_values(array_unique([...$v['locales'], $v['default_locale']]));

        return new self(strtolower($v['slug']), $v['name'], $v['country'] ?? null, strtoupper($v['currency_code']),
            $v['currency_symbol'], (string) $v['currency_rate'], $v['default_locale'], $locales, $v['timezone'],
            $r->boolean('is_active'), $v['admin_name'] ?? null, \App\Support\AdminPhone::digits($v['admin_country'] ?? 'EG', $v['admin_phone'] ?? '') , $v['admin_password'] ?? null);
    }

    public function toArray(): array
    {
        return ['slug' => $this->slug, 'name' => $this->name, 'country' => $this->country ? strtoupper($this->country) : null,
            'currency_code' => $this->currencyCode, 'currency_symbol' => $this->currencySymbol,
            'currency_rate' => $this->currencyRate, 'default_locale' => $this->defaultLocale,
            'locales' => $this->locales, 'timezone' => $this->timezone, 'is_active' => $this->isActive];
    }
}
