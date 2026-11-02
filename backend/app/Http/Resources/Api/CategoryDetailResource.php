<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category  Category page header: meta + breadcrumb (root -> self) + active children. */
class CategoryDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $node = fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'image' => $c->image_url, 'icon' => $c->icon];

        return $node($this->resource) + [
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'breadcrumbs' => $this->breadcrumbs()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])->all(),
            'children' => $this->children()->where('is_active', true)->get()->map($node)->all(),
        ];
    }
}
