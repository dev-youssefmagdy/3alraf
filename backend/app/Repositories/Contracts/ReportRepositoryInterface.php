<?php

namespace App\Repositories\Contracts;

use App\DTOs\Reports\ReportFilter;

/** Raw aggregate rows (arrays) per report. Money is already multiplied by tenants.currency_rate in central context. */
interface ReportRepositoryInterface
{
    public function purchases(ReportFilter $f): array;

    public function sales(ReportFilter $f): array;

    public function discounts(ReportFilter $f): array;

    public function shipping(ReportFilter $f): array;

    public function codSettlement(ReportFilter $f): array;

    public function paymentFees(ReportFilter $f): array;

    public function netProfit(ReportFilter $f): array;

    public function returns(ReportFilter $f): array;

    public function refunds(ReportFilter $f): array;

    public function expenses(ReportFilter $f): array;

    public function stock(ReportFilter $f): array;

    public function products(ReportFilter $f): array;

    public function categories(ReportFilter $f): array;

    public function affiliates(ReportFilter $f): array;

    public function flashSales(ReportFilter $f): array;

    public function customers(ReportFilter $f): array;

    public function branches(ReportFilter $f): array;

    public function paymentMethods(ReportFilter $f): array;

    /** @return array<string,int> status => count (all statuses) */
    public function statusCounts(ReportFilter $f): array;

    /** Sales per tenant (central dashboard). */
    public function tenantBreakdown(ReportFilter $f): array;

    public function newCustomers(ReportFilter $f): int;
}
