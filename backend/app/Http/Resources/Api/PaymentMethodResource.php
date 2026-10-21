<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PaymentMethod  (credentials are hidden on the model and never listed here) */
class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'type' => $this->type->value, 'provider' => $this->provider?->value, 'name' => $this->name,
            'instructions' => $this->instructions, 'account_number' => $this->type->value === 'wallet' ? $this->account_number : null,
            'requires_proof' => (bool) $this->requires_proof, 'fee_percent' => (string) $this->fee_percent, 'fee_fixed' => (string) $this->fee_fixed];
    }
}
