<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StaticPage */
class StaticPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['slug' => $this->slug, 'title' => $this->title];
    }
}
