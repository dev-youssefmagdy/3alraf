<?php

namespace App\Services;

use App\Models\FlashSaleItem;
use App\Repositories\Contracts\FlashSaleRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Flash sale CRUD; items are synced by (product, variant) so `sold` counters survive edits. */
class FlashSaleService extends CrudService
{
    public function __construct(FlashSaleRepositoryInterface $repo)
    {
        parent::__construct($repo);
    }

    public function create(object $dto): Model
    {
        return DB::transaction(function () use ($dto) {
            $sale = parent::create($dto);
            $this->syncItems($sale, $dto->items);

            return $sale;
        });
    }

    public function update(Model $model, object $dto): Model
    {
        return DB::transaction(function () use ($model, $dto) {
            parent::update($model, $dto);
            $this->syncItems($model, $dto->items);

            return $model;
        });
    }

    public function find(int|string $id): Model
    {
        return $this->repo->query()->with('items')->findOrFail($id);
    }

    private function syncItems(Model $sale, array $items): void
    {
        $keep = [];
        foreach ($items as $i) {
            $row = FlashSaleItem::firstOrNew(['flash_sale_id' => $sale->id, 'product_id' => $i['product_id'], 'product_variant_id' => $i['variant_id']]);
            $row->fill(['discount_type' => $i['discount_type'], 'value' => $i['value'], 'qty_limit' => $i['qty_limit']])->save();
            $keep[] = $row->id;
        }
        $sale->items()->whereNotIn('id', $keep)->delete();
    }
}
