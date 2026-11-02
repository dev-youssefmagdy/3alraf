<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Storefront\StockRequestRequest;
use App\Models\StockRequest;
use Illuminate\Http\JsonResponse;

/** "Notify me when back in stock": same rules and de-duplication as the web form (Storefront\StockRequestController). */
class StockRequestController extends ApiController
{
    public function store(StockRequestRequest $r): JsonResponse
    {
        $v = $r->validated();
        $variant = (int) ($v['variant_id'] ?? 0) ?: null;

        StockRequest::firstOrCreate(
            ['product_id' => $v['product_id'], 'product_variant_id' => $variant, 'phone' => $v['phone'], 'status' => 'new'],
            ['name' => $v['name'], 'phone_country_code' => $v['phone_country_code'] ?? null, 'note' => $v['note'] ?? null, 'customer_id' => $r->user()?->id],
        );

        return $this->respond(null, __('store.request_sent'), status: 201);
    }
}
