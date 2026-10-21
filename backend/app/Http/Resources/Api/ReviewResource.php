<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Review */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'rating' => $this->rating, 'body' => $this->body, 'status' => $this->status->value,
            'author' => $this->relationLoaded('customer') ? $this->customer?->name : null, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
