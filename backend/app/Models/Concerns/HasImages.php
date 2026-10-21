<?php

namespace App\Models\Concerns;

use App\Models\Image;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasImages
{
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')->orderBy('sort_order')->orderBy('id');
    }

    public function primaryImageUrl(bool $thumb = true): ?string
    {
        $img = $this->images->first();

        return $img ? ($thumb ? $img->thumb_url : $img->url) : null;
    }
}
