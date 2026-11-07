<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BlogPost  (`content` is HTML; only included on the detail endpoint) */
class BlogPostResource extends JsonResource
{
    public function __construct($resource, private bool $withContent = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug, 'title' => $this->title, 'excerpt' => $this->excerpt, 'meta_description' => $this->meta_description,
            'published_at' => ($this->published_at ?? $this->created_at)?->toIso8601String(),
            'url' => url(app()->getLocale().'/blog/'.$this->slug),
        ] + ($this->withContent ? ['content' => $this->content] : []);
    }
}
