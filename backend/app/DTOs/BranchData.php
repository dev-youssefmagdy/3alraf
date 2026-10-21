<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\BranchRequest;

final readonly class BranchData
{
    public function __construct(public array $name, public ?int $cityId, public ?string $address, public ?string $phone, public bool $isDefault, public bool $isActive) {}

    public static function fromRequest(BranchRequest $r): self
    {
        return new self($r->cleanTranslations($r->input('name')), $r->input('city_id') ? (int) $r->input('city_id') : null, $r->input('address'), $r->input('phone'), $r->boolean('is_default'), $r->boolean('is_active'));
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'city_id' => $this->cityId, 'address' => $this->address, 'phone' => $this->phone, 'is_default' => $this->isDefault, 'is_active' => $this->isActive];
    }
}
