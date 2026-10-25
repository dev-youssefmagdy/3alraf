<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CustomPage  (filters / pinned products stay internal; the grid is served by custom-pages/{slug}) */
class CustomPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['slug' => $this->slug, 'title' => $this->title, 'url' => tenant_url(current_tenant(), '/'.$this->slug)];
    }
}
