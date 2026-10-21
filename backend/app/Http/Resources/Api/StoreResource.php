<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Tenant */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug, 'name' => $this->name, 'country' => $this->country,
            'currency' => ['code' => $this->currency_code, 'symbol' => $this->currency_symbol], 'timezone' => $this->timezone,
            'default_locale' => $this->default_locale, 'locales' => collect($this->locales)->map(fn ($l) => ['code' => $l, 'rtl' => $this->isRtl($l)])->values(),
            'cod_collect_shipping_upfront' => $this->codCollectsShippingUpfront(),
            'prices_include_tax' => (bool) ($this->settings['prices_include_tax'] ?? false),
        ];
    }
}
