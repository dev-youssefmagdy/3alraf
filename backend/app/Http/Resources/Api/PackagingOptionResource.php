<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PackagingOption  (cost is merchant-only and never exposed) */
class PackagingOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'description' => $this->description, 'image' => $this->imageUrl(), 'price' => (string) $this->price];
    }
}
