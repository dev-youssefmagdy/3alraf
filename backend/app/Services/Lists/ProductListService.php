<?php

namespace App\Services\Lists;

use App\DTOs\ProductListData;
use App\Enums\ProductListType;
use App\Repositories\Contracts\ProductListItemRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Support\Facades\DB;

/** Admin pins (ordered) + the per-list limit (stored in tenant settings.list_limits). */
class ProductListService
{
    public function __construct(private ProductListItemRepositoryInterface $items, private TenantRepositoryInterface $tenants, private ProductListResolver $resolver) {}

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
            $tenant = current_tenant();
            $settings = $tenant->settings ?? [];
            $settings['list_limits'][$d->type->value] = $d->limit;
            $this->tenants->update($tenant, ['settings' => $settings]);
        });
    }
}
