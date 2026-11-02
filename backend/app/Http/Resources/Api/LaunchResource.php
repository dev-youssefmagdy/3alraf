<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SmallBatchTest  Public small-batch launch page (interest / survey / limited stock / pre-order). */
class LaunchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $open = $this->status === 'running' && (! $this->ends_on || ! $this->ends_on->isPast()) && (! $this->starts_on || ! $this->starts_on->isFuture());
        $product = $this->product_id ? \App\Models\Product::find($this->product_id) : null;

        return [
            'code' => $this->code, 'name' => $this->name, 'description' => $this->description, 'mode' => $this->mode,
            'survey_question' => $this->mode === 'survey' ? $this->survey_question : null,
            'preorder' => (bool) $this->preorder, 'preorder_terms' => $this->preorder ? $this->preorder_terms : null,
            'is_open' => $open, 'ends_on' => $this->ends_on?->toDateString(),
            'product' => $product ? ['id' => $product->id, 'slug' => $product->slug, 'name' => $product->name, 'image' => $product->primaryImageUrl()] : null,
        ];
    }
}
