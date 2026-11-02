<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Brand  (`sellable_count` loaded by the controller) */
class BrandDetailResource extends BrandResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + ['products_count' => (int) ($this->sellable_count ?? 0)];
    }
}
