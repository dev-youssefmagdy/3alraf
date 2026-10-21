<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Resource over CatalogSearch::facets(). */
class FacetsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $f = $this->resource;

        return [
            'brands' => BrandResource::collection($f['brands']),
            'attributes' => $f['attributes']->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'values' => $a->values->map(fn ($v) => ['id' => $v->id, 'label' => $v->value, 'color' => $v->color_hex])->values()])->values(),
            'price' => $f['price'], 'sorts' => \App\DTOs\Storefront\CatalogFilter::SORTS,
        ];
    }
}
