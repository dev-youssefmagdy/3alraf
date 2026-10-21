<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Resource over CatalogSearch::suggest(). */
class SuggestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $s = $this->resource;

        return [
            'products' => collect($s['products'])->map(fn ($p) => ['name' => $p['name'], 'slug' => basename($p['url']), 'image' => $p['image'], 'price' => $p['price']])->values(),
            'categories' => collect($s['categories'])->map(fn ($c) => ['name' => $c['name'], 'slug' => basename($c['url'])])->values(),
        ];
    }
}
