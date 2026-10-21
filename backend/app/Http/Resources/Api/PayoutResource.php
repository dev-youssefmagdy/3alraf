<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AffiliatePayout */
class PayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'amount' => (string) $this->amount, 'status' => $this->status->value, 'note' => $this->note,
            'requested_at' => $this->created_at?->toIso8601String(), 'paid_at' => $this->paid_at?->toIso8601String()];
    }
}
