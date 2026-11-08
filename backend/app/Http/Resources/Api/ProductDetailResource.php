<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Resource over the array built by StoreController: product, media (ProductGallery), picker (ProductPageData), summary, reviews, related, in_wishlist, can_review. */
class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $p = $r['product'];

        return [
            'id' => $p->id, 'slug' => $p->slug, 'name' => $p->name, 'description' => $p->description, 'sku' => $p->sku, 'barcode' => $p->barcode,
            'type' => $p->type->value, 'brand' => $p->brand ? ['id' => $p->brand->id, 'name' => $p->brand->name, 'logo' => $p->brand->logo_url] : null,
            'categories' => $p->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])->values(),
            'images' => $p->images->map(fn ($i) => ['url' => $i->url, 'thumb' => $i->thumb_url])->values(),
            // additive (P8): images and videos in gallery order; `images` above stays for older app builds
            'media' => $r['media']->map(fn (array $m) => ['id' => $m['id'], 'type' => $m['type'], 'url' => $m['url'], 'thumb' => $m['thumb'], 'poster' => $m['poster'], 'mime' => $m['mime'], 'sort_order' => $m['sort_order']])->values(),
            'price' => (string) $r['product']->effectivePrice(), 'regular_price' => (string) $r['product']->price,
            // exact per-variant price/stock (flash + variant overrides applied by PriceResolver); the cart re-prices authoritatively
            'options' => $r['picker']['options'], 'variants' => $r['picker']['variants'],
            'rating' => ['average' => $r['summary']['avg'], 'count' => $r['summary']['count']],
            'reviews' => ReviewResource::collection($r['reviews']),
            'related' => ProductCardResource::collection($r['related']),
            'in_wishlist' => (bool) $r['in_wishlist'], 'can_review' => (bool) $r['can_review'],
        ];
    }
}
