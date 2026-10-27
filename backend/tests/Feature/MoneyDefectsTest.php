<?php

use App\DTOs\Orders\CheckoutData;
use App\DTOs\Orders\ReturnRequestData;
use App\DTOs\Reports\ReportFilter;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\City;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Services\Orders\CarrierSettlementService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\ReturnService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Validation\ValidationException;

/** Regression tests for the money defects fixed 2026-10-25 (docs/CALCULATIONS.md). */
beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = codUpfront(Tenant::where('slug', 'egy')->first(), false);
    app()->instance('currentTenant', $this->tenant);
    $this->t = 'http://egy.3alraf.test/admin';
    $this->branch = Branch::first();
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج', 'en' => 'P'], 'slug' => 'md', 'sku' => 'MD']);
    $this->tp = TenantProduct::create(['product_id' => $this->product->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record($this->branch->id, $this->product->id, 0, T::PURCHASE_IN, 50, '60.00');
    $this->company = ShippingCompany::first();
    $this->place = fn (array $lines) => app(OrderPlacementService::class)->place(new CheckoutData(name: 'Ali', phone: '01000000100', cityId: City::first()->id, address: 'St',
        shippingCompanyId: $this->company->id, paymentMethodId: PaymentMethod::where('type', 'cod')->first()->id), $lines)['order'];
    $this->line = fn (int $qty) => ['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => $qty];
    $this->deliver = function (Order $o) {
        foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
            app(OrderStatusService::class)->change($o, $to);
        }

        return $o->refresh();
    };
    $this->filter = fn (?int $branchId = null) => new ReportFilter(now()->subDay()->toDateString(), now()->toDateString(), false, [$this->tenant->id], $branchId);
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
});

it('does not subtract the returns of a fully-returned order a second time in the net profit report', function () {
    $o = ($this->deliver)(($this->place)([($this->line)(2)]));
    $rs = app(ReturnService::class);
    $r = $rs->request($o, new ReturnRequestData(quantities: [$o->items->first()->id => 2], reason: 'x', restock: true, refundShipping: false, returnShippingCost: '5.00'));
    $rs->approve($r);
    expect($o->fresh()->status)->toBe(S::RETURNED);

    $d = app(ReportRepositoryInterface::class)->netProfit(($this->filter)());
    expect((int) $d['orders'])->toBe(0)->and((float) $d['return_value'])->toBe(0.0)->and((float) $d['returned_cogs'])->toBe(0.0)->and((float) $d['return_shipping'])->toBe(5.0);
    $np = $this->get("{$this->t}/reports/net-profit")->assertOk()->viewData('data');
    expect(collect($np['cards'])->firstWhere('label', __('reports.card.net_profit'))['value'])->toBe('-5.00');   // only the real return shipping cost
});

it('locks the return row: a stale double approve / double refund never restocks or refunds twice', function () {
    $o = ($this->deliver)(($this->place)([($this->line)(2)]));
    $rs = app(ReturnService::class);
    $stale = $rs->request($o, ReturnRequestData::fromArray(['items' => [$o->items->first()->id => ['quantity' => 1]], 'restock' => true]));
    $onHand = fn () => StockLevel::where('product_id', $this->product->id)->value('on_hand');
    $before = $onHand();
    $rs->approve($stale);
    expect(fn () => $rs->approve($stale))->toThrow(ValidationException::class);   // $stale still says "requested"
    expect($onHand())->toBe($before + 1)->and($o->items->first()->fresh()->returned_qty)->toBe(1);

    $approved = $stale->fresh();
    $rs->refund($approved, '50');
    expect(fn () => $rs->refund($approved, '50'))->toThrow(ValidationException::class);
    expect((string) $o->fresh()->refunded_amount)->toBe('50.00');

    // approve re-checks returned_qty + qty <= quantity on the fresh line
    $r2 = $rs->request($o->fresh(), ReturnRequestData::fromArray(['items' => [$o->items->first()->id => ['quantity' => 1]]]));
    $o->items->first()->forceFill(['returned_qty' => 2])->save();
    expect(fn () => $rs->approve($r2))->toThrow(ValidationException::class);
});

it('shares a product-wide flash item across cart lines: total flash units never exceed remaining', function () {
    $sale = FlashSale::create(['name' => ['ar' => 'عرض'], 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    $fi = FlashSaleItem::create(['flash_sale_id' => $sale->id, 'product_id' => $this->product->id, 'product_variant_id' => 0, 'discount_type' => 'price', 'value' => 80, 'qty_limit' => 3]);

    $o = ($this->place)([($this->line)(2), ($this->line)(2)]);   // 2 @80 + (1 @80 + 1 @100)
    expect((string) $o->subtotal)->toBe('340.00')->and($o->items->where('flash_sale_item_id', $fi->id)->sum('quantity'))->toBe(3)
        ->and($fi->fresh()->sold)->toBe(3);
    $o2 = ($this->place)([($this->line)(1)]);                     // sold out
    expect((string) $o2->subtotal)->toBe('100.00')->and($fi->fresh()->sold)->toBe(3);
});

it('excludes VAT of tax-inclusive orders from order profit and report revenue', function () {
    $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], ['tax_rate' => 14, 'prices_include_tax' => true])]);
    app()->instance('currentTenant', $this->tenant->refresh());
    $this->tp->update(['price' => 114, 'cost' => 50]);
    $o = ($this->place)([($this->line)(1)]);
    expect((string) $o->tax)->toBe('14.00')->and($o->taxIncluded())->toBeTrue();
    app(OrderStatusService::class)->change($o, S::CONFIRMED);
    $o->refresh();
    $expected = bcsub(bcadd('50.00', (string) $o->shipping_net, 2), bcadd((string) $o->shipping_cost, bcsub((string) $o->payment_fee, (string) $o->payment_fee_charged, 2), 2), 2); // 100 − 50 + shipping …
    expect((string) $o->net_profit)->toBe($expected);
    expect(app(ReportRepositoryInterface::class)->netProfit(($this->filter)())['revenue'])->toBe('100.00');
});

it('uses confirmed-order net_profit in the branches and tenant breakdowns, and respects the branch on carrier settlement', function () {
    $o = ($this->place)([($this->line)(1)]);
    app(OrderStatusService::class)->change($o, S::CONFIRMED);   // confirmed, not delivered
    $profit = (string) $o->refresh()->net_profit;
    expect((float) $profit)->toBeGreaterThan(0);
    $repo = app(ReportRepositoryInterface::class);
    expect($repo->branches(($this->filter)())[0]['profit'])->toBe($profit)->and($repo->tenantBreakdown(($this->filter)())[0]['profit'])->toBe($profit);

    $o->forceFill(['status' => S::DELIVERED, 'delivered_at' => now()])->save();
    $other = Branch::create(['name' => ['ar' => 'فرع 2'], 'is_active' => true, 'is_default' => false]);
    $svc = app(CarrierSettlementService::class);
    $from = now()->subDay()->toDateString();
    $to = now()->toDateString();
    expect($svc->settle($this->company->id, $from, $to, $other->id))->toBe(0)->and($o->fresh()->carrier_settled_at)->toBeNull();
    $this->post("{$this->t}/reports/cod-settlement/settle", ['shipping_company_id' => $this->company->id, 'from' => $from, 'to' => $to, 'branch_id' => $other->id])->assertRedirect();
    expect($o->fresh()->carrier_settled_at)->toBeNull();
    expect($svc->settle($this->company->id, $from, $to, $this->branch->id))->toBe(1);
});
