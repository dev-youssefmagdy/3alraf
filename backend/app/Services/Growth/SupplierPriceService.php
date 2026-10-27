<?php

namespace App\Services\Growth;

use App\Enums\PurchaseStatus;
use App\Models\ProductSupplyTerm;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierProductPrice;

/**
 * Supplier price comparison for reorder drafts. Two layers: (1) derived from RECEIVED purchase orders (last / avg / min unit_cost per supplier),
 * (2) explicit supplier_product_prices (valid today). Explicit wins over history for the same supplier. Never writes tenant_products.cost.
 * Unit cost only: PO shipping/other costs are not allocated into unit cost (CALCULATIONS.md, purchases).
 */
class SupplierPriceService
{
    /** @return array<int,array{last:float,avg:float,min:float,n:int,last_at:?string}> keyed by supplier_id */
    public function history(int $productId): array
    {
        $rows = PurchaseOrderItem::query()->join('purchase_orders as po', 'po.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('po.tenant_id', current_tenant()?->id)
            ->where('purchase_order_items.product_id', $productId)->where('po.status', PurchaseStatus::RECEIVED->value)
            ->orderBy('po.received_at')->orderBy('po.id')
            ->get(['po.supplier_id', 'purchase_order_items.unit_cost', 'po.received_at']);
        $out = [];
        foreach ($rows->groupBy('supplier_id') as $sid => $g) {
            $costs = $g->map(fn ($r) => (float) $r->unit_cost);
            $out[(int) $sid] = ['last' => $costs->last(), 'avg' => round($costs->avg(), 2), 'min' => $costs->min(), 'n' => $costs->count(), 'last_at' => $g->last()->received_at ? (string) $g->last()->received_at : null];
        }

        return $out;
    }

    /**
     * Ranked supplier options for one product. Rank: fits the lead-time need first, then lowest unit cost, then preferred.
     *
     * @return array<int,array<string,mixed>>
     */
    public function options(int $productId, float $productCost, float $price, float $marginFloor, int $leadNeed, int $defaultLead = 14): array
    {
        $hist = $this->history($productId);
        $explicit = SupplierProductPrice::where('product_id', $productId)->activeOn(now()->toDateString())->get()->groupBy('supplier_id')
            ->map(fn ($g) => $g->sortBy('unit_cost')->first());
        $term = ProductSupplyTerm::where('product_id', $productId)->first();
        $names = Supplier::where('is_active', true)->whereIn('id', array_unique(array_merge(array_keys($hist), $explicit->keys()->all())))->pluck('name', 'id');
        $opts = [];
        foreach ($names as $sid => $name) {
            $e = $explicit[$sid] ?? null;
            $h = $hist[$sid] ?? null;
            $cost = $e ? (float) $e->unit_cost : (float) $h['last'];
            $lead = $e?->lead_time_days ?? ($term && (int) $term->supplier_id === (int) $sid ? $term->lead_time_days : null) ?? $defaultLead;
            $margin = ($price > 0 && $cost > 0) ? round(($price - $cost) / $price * 100, 1) : null;
            $opts[] = ['supplier_id' => (int) $sid, 'name' => $name, 'source' => $e ? 'explicit' : 'history', 'unit_cost' => $cost, 'moq' => $e ? (int) $e->moq : 1, 'lead_time_days' => (int) $lead,
                'preferred' => (bool) ($e?->is_preferred), 'history' => $h, 'fits_lead' => $lead <= $leadNeed,
                'variance_pct' => $productCost > 0 ? round(($cost - $productCost) / $productCost * 100, 1) : null, 'margin_pct' => $margin,
                'below_margin_floor' => $margin !== null && $margin < $marginFloor];
        }
        usort($opts, fn ($a, $b) => [! $a['fits_lead'], $a['unit_cost'], ! $a['preferred']] <=> [! $b['fits_lead'], $b['unit_cost'], ! $b['preferred']]);

        return $opts;
    }

    /** Current quoted unit cost of one supplier for a product (explicit valid price, else last received cost), or null. */
    public function costFor(int $supplierId, int $productId): ?float
    {
        $e = SupplierProductPrice::where('supplier_id', $supplierId)->where('product_id', $productId)->activeOn(now()->toDateString())->orderBy('unit_cost')->first();
        if ($e) {
            return (float) $e->unit_cost;
        }

        return $this->history($productId)[$supplierId]['last'] ?? null;
    }

    public function save(array $d, ?int $adminId): SupplierProductPrice
    {
        Supplier::findOrFail((int) $d['supplier_id']);
        $row = SupplierProductPrice::create(['supplier_id' => (int) $d['supplier_id'], 'product_id' => (int) $d['product_id'], 'variant_id' => 0, 'unit_cost' => $d['unit_cost'], 'moq' => max(1, (int) ($d['moq'] ?? 1)),
            'lead_time_days' => $d['lead_time_days'] ?? null, 'valid_from' => $d['valid_from'] ?? null, 'valid_to' => $d['valid_to'] ?? null, 'is_preferred' => (bool) ($d['is_preferred'] ?? false), 'note' => $d['note'] ?? null]);
        if ($row->is_preferred) {
            SupplierProductPrice::where('product_id', $row->product_id)->where('id', '!=', $row->id)->update(['is_preferred' => false]);
        }
        app(ActionLogger::class)->log('inventory.supplier_price_saved', 'admin', $adminId, null, $row);

        return $row;
    }

    public function delete(int $id, ?int $adminId): void
    {
        $row = SupplierProductPrice::findOrFail($id);
        app(ActionLogger::class)->log('inventory.supplier_price_deleted', 'admin', $adminId, null, $row);
        $row->delete();
    }
}
