<?php

namespace App\Repositories\Eloquent;

use App\Enums\PurchaseStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Illuminate\Support\Collection;

class PurchaseOrderRepository extends BaseRepository implements PurchaseOrderRepositoryInterface
{
    protected string $model = PurchaseOrder::class;

    public function receivedItemsForProduct(int $productId): Collection
    {
        return PurchaseOrderItem::query()
            ->select('purchase_order_items.*')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_order_items.product_id', $productId)
            ->where('purchase_orders.status', PurchaseStatus::RECEIVED->value)
            ->with(['variant.values', 'purchaseOrder.supplier'])
            ->orderByDesc('purchase_orders.received_at')
            ->orderByDesc('purchase_order_items.id')
            ->get();
    }
}
