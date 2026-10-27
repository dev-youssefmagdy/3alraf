<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Affiliate */
class AffiliateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone, 'phone_country_code' => $this->phone_country_code, 'phone_full' => $this->phone_full, 'status' => $this->status->value,
            'ref' => $this->uuid]; // send as X-Affiliate-Ref when sharing links
    }
}
