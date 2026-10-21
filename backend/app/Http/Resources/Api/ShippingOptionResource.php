<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Resource over a shipping option array (fee/total come from OrderCalculator via OrderQuoteService). */
class ShippingOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $o = $this->resource;

        return ['shipping_company_id' => $o['id'], 'name' => $o['name'], 'eta_days' => $o['eta'], 'supports_cod' => (bool) $o['supports_cod'],
            'fee' => $o['fee'], 'free' => (bool) $o['free'], 'order_total' => $o['total']];
    }
}
