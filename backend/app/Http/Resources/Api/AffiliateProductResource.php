<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** TenantProduct with ->affiliate_url (AffiliateService::products). */
class AffiliateProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $p = $this->product;

        return ['id' => $p->id, 'slug' => $p->slug, 'name' => $p->name, 'image' => $p->primaryImageUrl(), 'price' => (string) $this->effectivePrice(),
            'link' => $this->affiliate_url, 'qr_url' => api_route('affiliate.qr', ['slug' => $p->slug])];
    }
}
