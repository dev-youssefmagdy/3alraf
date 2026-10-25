<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Banner */
class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'placement' => $this->placement->value, 'title' => $this->title, 'image' => $this->imageUrl(), 'link' => $this->safeLink()];
    }
}
