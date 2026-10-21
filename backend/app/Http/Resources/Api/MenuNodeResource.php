<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Resource over a StorefrontData::menu() node (array). */
class MenuNodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $n = $this->resource;

        return ['id' => $n['id'], 'name' => $n['name'], 'slug' => $n['slug'], 'image' => $n['image'], 'icon' => $n['icon'],
            'children' => self::collection($n['children'])];
    }
}
