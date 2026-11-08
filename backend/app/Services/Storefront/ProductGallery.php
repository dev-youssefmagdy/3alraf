<?php

namespace App\Services\Storefront;

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVideo;
use Illuminate\Support\Collection;

/** The product's public images and videos in one display order (sort_order, image before video on a tie). Only `images` + `product_videos`, never the private social-media library. */
class ProductGallery
{
    /** @return Collection<int,array{key:string,type:string,id:int,url:string,thumb:?string,poster:?string,mime:?string,sort_order:int}> sort_order = position in the gallery */
    public function items(Product $product): Collection
    {
        $images = $product->images->map(fn (Image $i) => ['key' => "image:$i->id", 'type' => 'image', 'id' => $i->id, 'url' => $i->url, 'thumb' => $i->thumb_url, 'poster' => null, 'mime' => null, 'stored' => (int) $i->sort_order, 'rank' => 0]);
        $videos = $product->videos->map(fn (ProductVideo $v) => ['key' => "video:$v->id", 'type' => 'video', 'id' => $v->id, 'url' => $v->url, 'thumb' => $v->poster_thumb_url, 'poster' => $v->poster_url, 'mime' => $v->mime, 'stored' => $v->sort_order, 'rank' => 1]);

        return collect($images)->concat($videos)->sortBy([['stored', 'asc'], ['rank', 'asc'], ['id', 'asc']])->values()
            ->map(fn (array $m, int $position) => array_diff_key($m, ['stored' => 1, 'rank' => 1]) + ['sort_order' => $position]);
    }
}
