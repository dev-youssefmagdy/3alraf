<?php

namespace App\Repositories\Contracts;

use App\DTOs\Reports\ReportFilter;

/**
 * Read-only aggregates for the marketer panel / Marketing internal API (P4). Sales = admin-confirmed orders (OrderStatus::salesStatuses())
 * bucketed by order created_at, item revenue = (line_subtotal − line_discount) pro-rated to the units not returned — the same definitions as the
 * Top products report (docs/CALCULATIONS.md §Reports). No customer data is ever selected.
 */
interface MarketingRepositoryInterface
{
    /**
     * Per product (or per product+variant with $byVariant): units (net of returns), orders, revenue, revenue_costed / cost (lines whose
     * unit_cost snapshot > 0 only), uncosted_units, returned_units, last_sold_at.
     *
     * @return array<string,array> keyed "product_id" or "product_id:variant_id"
     */
    public function itemSales(ReportFilter $f, ?array $productIds = null, bool $byVariant = false): array;

    /** Sellable stock per product over ACTIVE branches: on_hand, reserved, available (on_hand − reserved), stocked (has any stock row). */
    public function stockByProduct(?array $productIds = null): array;

    /** Same per product+variant ("product_id:variant_id"). */
    public function stockByVariant(array $productIds): array;

    /** Last sale date (any time) per product among sales-status orders. @return array<int,string> */
    public function lastSoldAt(array $productIds): array;

    /** Order counts by channel (sales statuses, range). @return array<string,int> */
    public function ordersByChannel(ReportFilter $f): array;

    /** Cancelled orders created in the range: total + by customer reason code (null = admin / no code). */
    public function cancellations(ReportFilter $f): array;

    /** Coupon usages in the range per discount code id: usages, discount (Σ usage amount), orders_total. Sales-status orders only. */
    public function couponUsage(ReportFilter $f): array;

    /** Component units (net of returns) sold as part of each bundle in the range (sales-status orders). @return array<int,int> bundle id => units */
    public function bundleSales(ReportFilter $f): array;
}
