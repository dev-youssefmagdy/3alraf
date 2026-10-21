<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\ExpenseCategoryRequest;

final readonly class ExpenseCategoryData
{
    public function __construct(public array $name, public bool $isActive) {}

    public static function fromRequest(ExpenseCategoryRequest $r): self
    {
        return new self($r->cleanTranslations($r->input('name')), $r->boolean('is_active'));
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'is_active' => $this->isActive];
    }
}
