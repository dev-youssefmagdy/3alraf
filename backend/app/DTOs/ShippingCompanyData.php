<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\ShippingCompanyRequest;

final readonly class ShippingCompanyData
{
    /** @param array<int,array<string,mixed>> $rates */
    public function __construct(public array $name, public int $sortOrder, public bool $isActive, public array $rates) {}

    public static function fromRequest(ShippingCompanyRequest $r): self
    {
        $n = fn ($x) => ($x === null || $x === '') ? null : $x;
        $rates = collect($r->input('rates', []))->map(fn ($x) => [
            'city_id' => (int) $x['city_id'], 'price' => (string) $x['price'], 'cost' => $n($x['cost'] ?? null), 'free_over' => $n($x['free_over'] ?? null),
            'eta_min_days' => $n($x['eta_min_days'] ?? null), 'eta_max_days' => $n($x['eta_max_days'] ?? null),
            'supports_cod' => filter_var($x['supports_cod'] ?? false, FILTER_VALIDATE_BOOL),
        ])->values()->all();

        return new self($r->cleanTranslations($r->input('name')), (int) $r->input('sort_order', 0), $r->boolean('is_active'), $rates);
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'sort_order' => $this->sortOrder, 'is_active' => $this->isActive];
    }
}
