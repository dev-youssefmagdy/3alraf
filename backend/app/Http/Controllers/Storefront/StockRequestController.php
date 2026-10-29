<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StockRequestRequest;
use App\Models\StockRequest;
use Illuminate\Http\JsonResponse;

class StockRequestController extends Controller
{
    public function store(StockRequestRequest $r): JsonResponse
    {
        $v = $r->validated();
        $variant = (int) ($v['variant_id'] ?? 0) ?: null;

        // the same person asking twice for the same item while it is still open is a no-op
        StockRequest::firstOrCreate(
            ['product_id' => $v['product_id'], 'product_variant_id' => $variant, 'phone' => $v['phone'], 'status' => 'new'],
            ['name' => $v['name'], 'phone_country_code' => $v['phone_country_code'] ?? null, 'note' => $v['note'] ?? null, 'customer_id' => auth('web')->id()],
        );

        return $this->ok(__('store.request_sent'));
    }
}
