<?php

namespace App\DTOs;

use App\Http\Requests\Admin\GeneralSettingsRequest;

final readonly class GeneralSettingsData
{
    /** @param list<string> $locales */
    public function __construct(
        public string $name,
        public ?string $country,
        public string $currencyCode,
        public string $currencySymbol,
        public string $defaultLocale,
        public array $locales,
        public string $timezone,
        public bool $giftFinderEnabled,
        public bool $customerCancelEnabled,
    ) {}

    public static function fromRequest(GeneralSettingsRequest $r): self
    {
        $v = $r->validated();

        return new self(trim($v['name']), isset($v['country']) && $v['country'] !== '' ? strtoupper($v['country']) : null,
            strtoupper($v['currency_code']), $v['currency_symbol'], $v['default_locale'], array_values(array_unique($v['locales'])),
            $v['timezone'], $r->boolean('gift_finder_enabled'), $r->boolean('customer_cancel_enabled'));
    }

    /** @return array<string,mixed> the plain `settings` rows */
    public function profile(): array
    {
        return ['name' => $this->name, 'country' => $this->country, 'currency_code' => $this->currencyCode, 'currency_symbol' => $this->currencySymbol,
            'default_locale' => $this->defaultLocale, 'locales' => $this->locales, 'timezone' => $this->timezone];
    }
}
