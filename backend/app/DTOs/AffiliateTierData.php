<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\AffiliateTierRequest;
use App\Support\Money;

final readonly class AffiliateTierData
{
    public function __construct(public string $name, public string $minSales, public string $percent) {}

    public static function fromRequest(AffiliateTierRequest $r): self
    {
        return new self($r->input('name'), Money::round((string) $r->input('min_sales')), Money::round((string) $r->input('percent')));
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'min_sales' => $this->minSales, 'percent' => $this->percent];
    }
}
