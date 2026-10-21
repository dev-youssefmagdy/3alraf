<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A decorated TenantProduct (CardPresenter sets ->display). */
class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $d = $this->display ?? [];
        $p = $this->product;

        return [
            'id' => $p->id, 'slug' => $p->slug, 'name' => $p->name, 'image' => $p->primaryImageUrl(),
            'price' => $d['price'] ?? (string) $this->effectivePrice(), 'old_price' => $d['old'] ?? null, 'discount_percent' => $d['percent'] ?? null,
            'flash' => (bool) ($d['flash'] ?? false), 'flash_ends_at' => $d['ends_at'] ?? null,
            'rating' => $d['rating'] ?? null, 'reviews_count' => $d['reviews'] ?? 0,
        ];
    }
}
