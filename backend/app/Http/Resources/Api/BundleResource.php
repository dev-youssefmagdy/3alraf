<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Resource over a BundleCatalog::present() array. `available` = whole bundles purchasable now (null = unlimited / backorder). */
class BundleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $b = $this->resource;

        return ['id' => $b['id'], 'slug' => $b['slug'], 'name' => $b['name'], 'description' => $b['description'], 'image' => $b['image'], 'price' => $b['price'],
            'regular_total' => $b['regular_total'], 'savings' => $b['savings'], 'in_stock' => $b['in_stock'], 'available' => $b['available'],
            'starts_at' => $b['starts_at'], 'ends_at' => $b['ends_at'],
            'components' => collect($b['components'])->map(fn ($c) => ['product_id' => $c['product_id'], 'variant_id' => $c['variant_id'], 'name' => $c['name'], 'slug' => $c['slug'],
                'quantity' => $c['quantity'], 'unit_price' => $c['unit_price'], 'image' => $c['image']])->values()];
    }
}
