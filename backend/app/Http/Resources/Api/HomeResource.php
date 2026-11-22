<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Resource over StorefrontData::home(). */
class HomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $h = $this->resource;
        $flash = $h['flash'];

        return [
            'banners' => ['hero' => BannerResource::collection($h['hero']), 'strips' => BannerResource::collection($h['strips']), 'tiles' => BannerResource::collection($h['tiles']),
                'popup' => $h['popup'] ? new BannerResource($h['popup']) : null],
            'categories' => MenuNodeResource::collection($h['categories']),
            'flash_sale' => $flash ? ['id' => $flash['sale']->id, 'name' => $flash['sale']->displayName(), 'ends_at' => $flash['sale']->ends_at->toIso8601String(), 'products' => ProductCardResource::collection($flash['items'])] : null,
            'rails' => [
                'recommended' => ProductCardResource::collection($h['rails']['recommended']),
                'most_sold' => ProductCardResource::collection($h['rails']['most_sold']),
                'most_reviewed' => ProductCardResource::collection($h['rails']['most_reviewed']),
            ],
            'category_rails' => collect($h['categoryRails'])->map(fn ($r) => ['id' => $r['category']->id, 'name' => $r['category']->name, 'slug' => $r['category']->slug,
                'products' => ProductCardResource::collection($r['items'])])->values(),
            'brands' => BrandResource::collection($h['brands']),
        ];
    }
}
