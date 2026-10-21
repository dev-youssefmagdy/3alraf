<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\CityRequest;

final readonly class CityData
{
    public function __construct(public array $name, public int $sortOrder, public bool $isActive) {}

    public static function fromRequest(CityRequest $r): self
    {
        return new self($r->cleanTranslations($r->input('name')), (int) $r->input('sort_order', 0), $r->boolean('is_active'));
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'sort_order' => $this->sortOrder, 'is_active' => $this->isActive];
    }
}
