<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderReturn */
class ReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['number' => $this->number, 'status' => $this->status->value, 'reason' => $this->reason, 'refund_amount' => (string) $this->refund_amount,
            'requested_at' => $this->created_at?->toIso8601String()];
    }
}
