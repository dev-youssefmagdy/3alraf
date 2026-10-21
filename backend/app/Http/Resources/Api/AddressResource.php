<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CustomerAddress */
class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'label' => $this->label, 'name' => $this->name, 'phone' => $this->phone, 'city_id' => $this->city_id,
            'city' => $this->relationLoaded('city') ? $this->city?->name : null, 'address' => $this->address, 'notes' => $this->notes, 'is_default' => (bool) $this->is_default];
    }
}
