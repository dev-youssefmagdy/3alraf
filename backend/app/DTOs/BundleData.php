<?php

namespace App\DTOs;

use App\Http\Requests\Tenant\BundleRequest;
use App\Services\Stock\StockItemCatalog;
use Illuminate\Http\UploadedFile;

final readonly class BundleData
{
    /** @param array<int,array{product_id:int,variant_id:int,quantity:int}> $items duplicates already merged */
    public function __construct(public array $name, public array $description, public string $price, public ?string $startsAt, public ?string $endsAt, public int $sortOrder,
        public bool $isActive, public array $items, public ?UploadedFile $image, public bool $removeImage) {}

    public static function fromRequest(BundleRequest $r): self
    {
        $catalog = app(StockItemCatalog::class);
        $merged = [];
        foreach ($r->input('items', []) as $row) {
            [$pid, $vid] = $catalog->split((string) $row['item']);
            $k = "$pid-$vid";
            $merged[$k] = ['product_id' => $pid, 'variant_id' => $vid, 'quantity' => ($merged[$k]['quantity'] ?? 0) + (int) $row['quantity']];
        }

        return new self($r->cleanTranslations($r->input('name')), $r->cleanTranslations($r->input('description')), (string) $r->input('price'),
            $r->input('starts_at') ?: null, $r->input('ends_at') ?: null, (int) $r->input('sort_order', 0), $r->boolean('is_active'),
            array_values($merged), $r->file('image'), $r->boolean('remove_image'));
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'description' => $this->description ?: null, 'price' => $this->price, 'starts_at' => $this->startsAt, 'ends_at' => $this->endsAt,
            'sort_order' => $this->sortOrder, 'is_active' => $this->isActive];
    }
}
