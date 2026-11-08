<?php

namespace App\Services\Lists;

use App\DTOs\ProductListData;
use App\Enums\ProductListType;
use App\Repositories\Contracts\ProductListItemRepositoryInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/** Admin pins (ordered) + the per-list limit (setting `list_limits`). */
class ProductListService
{
    public function __construct(private ProductListItemRepositoryInterface $items, private ProductListResolver $resolver) {}

    /** @return array<string,array{pinned:\Illuminate\Support\Collection,limit:int}> */
    public function overview(): array
    {
        $out = [];
        foreach (ProductListType::cases() as $type) {
            $out[$type->value] = [
                'pinned' => $this->items->query()->where('list_type', $type->value)->with('product')->orderBy('sort_order')->orderBy('id')->get(),
                'limit' => $this->resolver->limitFor($type),
            ];
        }

        return $out;
    }

    public function save(ProductListData $d): void
    {
        DB::transaction(function () use ($d) {
            $this->items->query()->where('list_type', $d->type->value)->delete();
            foreach ($d->productIds as $i => $pid) {
                $this->items->create(['list_type' => $d->type->value, 'product_id' => $pid, 'sort_order' => $i]);
            }
            $limits = (array) setting('list_limits', []);
            $limits[$d->type->value] = $d->limit;
            Setting::put('list_limits', $limits);
        });
    }

    /** Pinned product ids of a list in display order. @return int[] */
    public function pinnedIds(ProductListType $type): array
    {
        return $this->items->query()->where('list_type', $type->value)->orderBy('sort_order')->orderBy('id')->pluck('product_id')->map(fn ($v) => (int) $v)->all();
    }

    /** Pin a product at a 1-based position (null = last); keeps the other pins and the list limit (P5 product suggestions). */
    public function pin(ProductListType $type, int $productId, ?int $position = null): void
    {
        $ids = array_values(array_diff($this->pinnedIds($type), [$productId]));
        $at = $position === null ? count($ids) : max(0, min(count($ids), $position - 1));
        array_splice($ids, $at, 0, [$productId]);
        $this->save(new ProductListData($type, $ids, $this->resolver->limitFor($type)));
    }

    public function unpin(ProductListType $type, int $productId): void
    {
        $this->save(new ProductListData($type, array_values(array_diff($this->pinnedIds($type), [$productId])), $this->resolver->limitFor($type)));
    }

    /** Restore an exact pinned order (rollback). */
    public function restore(ProductListType $type, array $productIds): void
    {
        $this->save(new ProductListData($type, array_values(array_map('intval', $productIds)), $this->resolver->limitFor($type)));
    }
}
