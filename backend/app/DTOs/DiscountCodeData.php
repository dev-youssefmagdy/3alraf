<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\DiscountCodeRequest;

final readonly class DiscountCodeData
{
    public function __construct(
        public string $code, public string $type, public string $value, public string $minOrder, public ?string $maxDiscount,
        public ?int $usageLimit, public ?int $perCustomerLimit, public ?string $startsAt, public ?string $endsAt, public string $scope,
        public array $productIds, public array $categoryIds, public bool $freeShipping, public bool $isActive,
    ) {}

    public static function fromRequest(DiscountCodeRequest $r): self
    {
        $n = fn ($k) => $r->filled($k) ? (int) $r->input($k) : null;
        $scope = $r->input('scope', 'all');

        return new self(
            strtoupper(trim($r->input('code'))), $r->input('type'), (string) ($r->input('value') ?: '0'), (string) ($r->input('min_order') ?: '0'),
            $r->filled('max_discount') ? (string) $r->input('max_discount') : null, $n('usage_limit'), $n('per_customer_limit'),
            $r->input('starts_at') ?: null, $r->input('ends_at') ?: null, $scope,
            $scope === 'products' ? array_map('intval', $r->input('product_ids', [])) : [],
            $scope === 'categories' ? array_map('intval', $r->input('category_ids', [])) : [],
            $r->boolean('free_shipping'), $r->boolean('is_active'),
        );
    }

    public function toArray(): array
    {
        return ['code' => $this->code, 'type' => $this->type, 'value' => $this->value, 'min_order' => $this->minOrder, 'max_discount' => $this->maxDiscount,
            'usage_limit' => $this->usageLimit, 'per_customer_limit' => $this->perCustomerLimit, 'starts_at' => $this->startsAt, 'ends_at' => $this->endsAt,
            'scope' => $this->scope, 'product_ids' => $this->productIds ?: null, 'category_ids' => $this->categoryIds ?: null,
            'free_shipping' => $this->freeShipping, 'is_active' => $this->isActive];
    }
}
