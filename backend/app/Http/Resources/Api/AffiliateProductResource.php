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
            'link' => $this->affiliate_url,
            // stored PNG on the media CDN; the authenticated route below regenerates it on demand if it is missing
            'qr_url' => $this->qr_url ?? api_route('affiliate.qr', ['slug' => $p->slug]),
            'variants' => collect($this->variant_links)->map(fn ($v) => $v + ['qr_url' => $v['qr_url'] ?? api_route('affiliate.qr', ['slug' => $p->slug]).'?variant='.$v['id']])->all()];
    }
}
