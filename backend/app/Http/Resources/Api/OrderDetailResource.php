<?php

namespace App\Http\Resources\Api;

use App\Services\Orders\OrderPaymentService;
use Illuminate\Http\Request;

/** Full order for the order screen (items, timeline, returns, what the customer can do next). */
class OrderDetailResource extends OrderResource
{
    public function toArray(Request $request): array
    {
        $o = $this->resource;
        $payments = app(OrderPaymentService::class);
        $method = $o->payingMethod();
        $payable = $payments->payable($o);

        return parent::toArray($request) + [
            'customer' => ['name' => $o->customer_name, 'phone' => $o->customer_phone, 'email' => $o->customer_email],
            'city' => $o->city?->name, 'address' => $o->address, 'notes' => $o->notes, 'shipping_company' => $o->shippingCompany?->name,
            'prepay_method' => $o->prepayMethod ? ['id' => $o->prepayMethod->id, 'type' => $o->prepayMethod->type->value, 'name' => $o->prepayMethod->name] : null,
            'has_proof' => (bool) $o->payment_proof, 'pay_now' => $o->payableNow(),
            'can_pay_online' => $payable && $method?->type->value === 'online',
            'can_upload_proof' => $payments->canUploadProof($o),
            'items' => OrderItemResource::collection($o->items),
            'timeline' => $o->histories->map(fn ($h) => ['status' => $h->to_status->value, 'note' => $h->note, 'at' => $h->created_at?->toIso8601String()])->values(),
            'returns' => ReturnResource::collection($o->returns),
        ];
    }
}
