<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\FlashSaleRequest;
use App\Services\Stock\StockItemCatalog;

final readonly class FlashSaleData
{
    /** @param array<int,array{product_id:int,variant_id:int,discount_type:string,value:string,qty_limit:?int}> $items */
    public function __construct(public array $name, public string $startsAt, public string $endsAt, public bool $isActive, public array $items) {}

    public static function fromRequest(FlashSaleRequest $r): self
    {
        $catalog = app(StockItemCatalog::class);
        $items = collect($r->input('items', []))->map(function ($i) use ($catalog) {
            [$pid, $vid] = $catalog->split($i['item']);

            return ['product_id' => $pid, 'variant_id' => $vid, 'discount_type' => $i['discount_type'], 'value' => (string) $i['value'],
                'qty_limit' => ($i['qty_limit'] ?? '') === '' ? null : (int) $i['qty_limit']];
        })->values()->all();

        return new self($r->cleanTranslations($r->input('name')), $r->input('starts_at'), $r->input('ends_at'), $r->boolean('is_active'), $items);
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'starts_at' => $this->startsAt, 'ends_at' => $this->endsAt, 'is_active' => $this->isActive];
    }
}
