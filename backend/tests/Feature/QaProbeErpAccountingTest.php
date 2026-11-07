<?php

/*
 * Regression tests for the ERP accounting audit (docs/qa/ERP_AUDIT_FINDINGS.md). Born as the QA probes that proved ERP-001..009;
 * each now asserts the corrected accounting outcome (pass 1 fixes), plus regression tests for the other pass-1 fixes.
 */

use App\DTOs\Orders\CheckoutData;
use App\DTOs\Orders\ReturnRequestData;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Branch;
use App\Models\City;
use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\Owner;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Admin;
use App\Services\Affiliates\AffiliateService;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Backfill\BackfillService;
use App\Services\Erp\Owners\OwnerService;
use App\Services\Erp\Parties\PartyService;
use App\Services\Erp\Posting\SalesPoster;
use App\Services\Orders\CarrierSettlementService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\ReturnService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    codUpfront(false);
    $this->branch = Branch::first();
    $this->product = tap(Product::create(['type' => 'simple', 'name' => ['ar' => 'م', 'en' => 'qa'], 'slug' => 'qa-p', 'sku' => 'QAP', 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]), function ($p) {
        listProduct($p, ['price' => '100', 'cost' => '60', 'is_active' => true]);
        app(StockLedger::class)->record(Branch::first()->id, $p->id, 0, T::PURCHASE_IN, 100, '60');
    });
    $this->place = fn (int $qty = 2) => app(OrderPlacementService::class)->place(new CheckoutData(
        name: 'Ali', phone: '01000000100', cityId: City::first()->id, address: 'St', shippingCompanyId: ShippingCompany::first()->id,
        paymentMethodId: PaymentMethod::where('type', 'cod')->first()->id), [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => $qty]])['order'];
    $this->to = function (Order $o, S ...$steps) {
        foreach ($steps as $s) {
            app(OrderStatusService::class)->change($o, $s);
        }

        return $o->refresh();
    };
    $this->bal = fn (string $role) => (float) Account::where('role_key', $role)->value('current_balance');
    $this->ship = fn (Order $o) => ($this->to)($o, S::CONFIRMED, S::PROCESSING, S::SHIPPED);
    $this->actingAs(Admin::first(), 'admin');
});

it('REG ERP-001: a COD return refund is paid from cash, never credited to the Carrier COD receivable', function () {
    $o = ($this->to)(($this->place)(), S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED);
    app(CarrierSettlementService::class)->settle($o->shipping_company_id, now()->subDay()->toDateString(), now()->toDateString());
    expect(($this->bal)('carrier_receivable'))->toBe(0.0);                         // carrier paid us in full
    $svc = app(ReturnService::class);
    $r = $svc->approve($svc->request($o->refresh(), new ReturnRequestData([$o->items()->first()->id => 1], 'x', true, false)));
    $svc->refund($r);                                                               // merchant hands 100 back to the customer (cash)
    expect(($this->bal)('carrier_receivable'))->toBe(0.0, 'carrier owes us nothing; refund was booked against the carrier');
    expect(JournalEntry::where('idempotency_key', 'return:'.$r->id.':refund')->first()->lines()->where('account_id', Account::where('role_key', 'cash')->value('id'))->sum('credit'))->toEqual(100);
});

it('REG ERP-002: COGS is booked at the value that actually left stock, ledger inventory follows the stock ledger', function () {
    $o = ($this->to)(($this->place)(), S::CONFIRMED);
    app(StockLedger::class)->record($this->branch->id, $this->product->id, 0, T::PURCHASE_IN, 100, '120'); // avg 60 -> 90 before shipping
    $o = ($this->to)($o, S::PROCESSING, S::SHIPPED);
    $out = StockMovement::where('type', T::SALE_OUT->value)->where('reference_type', $o->getMorphClass())->where('reference_id', $o->id)->get();
    $stockOut = $out->sum(fn ($m) => (float) $m->unit_cost * abs((int) $m->quantity));
    expect((float) $o->cogs)->toBe($stockOut, 'ledger COGS/inventory credit != value that actually left stock');
    expect(($this->bal)('cogs'))->toBe($stockOut)->and(($this->bal)('inventory'))->toBe(-$stockOut);
    expect((float) $o->items()->first()->unit_cost)->toBe($stockOut / 2); // returns reverse at the shipped cost
});

it('REG ERP-002/011: legacy "confirmed" policy books the snapshot at confirmation and trues it up at shipment', function () {
    app(\App\Services\Erp\Equity\ErpSettings::class)->set('revenue_recognition', 'confirmed');
    $o = ($this->to)(($this->place)(), S::CONFIRMED);
    expect(($this->bal)('cogs'))->toBe(120.0);
    app(StockLedger::class)->record($this->branch->id, $this->product->id, 0, T::PURCHASE_IN, 100, '120');
    $o = ($this->to)($o, S::PROCESSING, S::SHIPPED);
    expect(($this->bal)('cogs'))->toBe((float) $o->cogs)->and((float) $o->cogs)->toBe(180.0)->and(($this->bal)('inventory'))->toBe(-180.0);
    expect(JournalEntry::where('idempotency_key', SalesPoster::trueUpKey($o))->exists())->toBeTrue();
});

it('REG ERP-011: "delivered" policy parks shipped cost in goods out for delivery and recognises on delivery', function () {
    app(\App\Services\Erp\Equity\ErpSettings::class)->set('revenue_recognition', 'delivered');
    $o = ($this->ship)(($this->place)());
    expect(($this->bal)('sales_revenue'))->toBe(0.0)->and(($this->bal)('goods_in_delivery'))->toBe(120.0)->and(($this->bal)('inventory'))->toBe(-120.0);
    $o = ($this->to)($o, S::DELIVERED);
    expect(($this->bal)('sales_revenue'))->toBe(200.0)->and(($this->bal)('goods_in_delivery'))->toBe(0.0)->and(($this->bal)('cogs'))->toBe(120.0);
});

it('REG ERP-011: a shipped parcel returned to sender is cancelled, restocked and its revenue/COGS reversed', function () {
    $o = ($this->ship)(($this->place)());
    $before = app(StockLedger::class)->onHand($this->branch->id, $this->product->id);
    expect(($this->bal)('receivable'))->toBe((float) $o->total);
    $o = ($this->to)($o, S::CANCELLED);
    expect($o->status)->toBe(S::CANCELLED)->and(app(StockLedger::class)->onHand($this->branch->id, $this->product->id))->toBe($before + 2);
    foreach (['receivable', 'sales_revenue', 'cogs', 'inventory', 'vat_payable'] as $r) {
        expect(($this->bal)($r))->toBe(0.0, $r);
    }
    // customers can never cancel a shipped order
    expect($o->status->cancellableBeforeShipping())->toBeFalse()->and(S::SHIPPED->cancellableBeforeShipping())->toBeFalse();
});

it('REG ERP-003: a paid affiliate payout clears the affiliate payable against the paying account', function () {
    $a = Affiliate::create(['name' => 'Aff', 'email' => 'aff-qa@egy.test', 'password' => 'password', 'status' => 'approved', 'approved_at' => now()]);
    $o = ($this->place)();
    $o->update(['affiliate_id' => $a->id, 'affiliate_commission' => '11.50']);
    $o = ($this->to)($o, S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED);
    AffiliateCommission::create(['affiliate_id' => $a->id, 'order_id' => $o->id, 'base' => '200', 'percent' => '5.75', 'amount' => '11.50', 'status' => 'approved', 'approved_at' => now()]);
    expect(($this->bal)('affiliate_payable'))->toBe(11.5);
    $svc = app(AffiliateService::class);
    $svc->markPaid($svc->requestPayout($a), null, 'bank');
    expect(($this->bal)('affiliate_payable'))->toBe(0.0, 'payout paid but liability still open');
    expect(($this->bal)('bank'))->toBe(-11.5);
});

it('REG ERP-004: a full return after the commission was PAID keeps the commission expense', function () {
    $a = Affiliate::create(['name' => 'Aff', 'email' => 'aff-qa2@egy.test', 'password' => 'password', 'status' => 'approved', 'approved_at' => now()]);
    $o = ($this->place)(1);
    $o->update(['affiliate_id' => $a->id, 'affiliate_commission' => '5.00']);
    $o = ($this->to)($o, S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED);
    AffiliateCommission::create(['affiliate_id' => $a->id, 'order_id' => $o->id, 'base' => '100', 'percent' => '5', 'amount' => '5.00', 'status' => 'approved', 'approved_at' => now()]);
    $svc = app(AffiliateService::class);
    $svc->markPaid($svc->requestPayout($a));
    $rs = app(ReturnService::class);
    $rs->approve($rs->request($o->refresh(), new ReturnRequestData([$o->items()->first()->id => 1], 'x', true, false)));
    expect(AffiliateCommission::where('order_id', $o->id)->value('status')->value)->toBe('paid');
    expect(($this->bal)('affiliate_expense'))->toBe(5.0, 'a paid (non-recoverable) commission was reversed out of expenses');
});

it('REG ERP-005: owner "distribution" transactions are refused (controlled workflow only)', function () {
    $owner = app(OwnerService::class)->save(['name' => 'Owner A', 'ownership_percent' => '100', 'is_eligible' => true]);
    // no profit at all, no approval, no cash check
    $threw = false;
    try {
        app(OwnerService::class)->transact($owner, 'distribution', '5000', now()->toDateString(), 'cash');
    } catch (\Throwable $e) {
        $threw = true;
    }
    expect($threw)->toBeTrue('distribution of 5000 with zero profit and zero cash was accepted');
});

it('REG ERP-006: branch capital allocation is a zero-sum internal move with a real source', function () {
    $owner = app(OwnerService::class)->save(['name' => 'Owner A', 'ownership_percent' => '100', 'is_eligible' => true]);
    expect(fn () => app(OwnerService::class)->allocateToBranch($this->branch->id, '5000', now()->toDateString()))->toThrow(\App\Exceptions\AccountingException::class); // no cash at HQ
    app(OwnerService::class)->transact($owner, 'contribution', '5000', now()->toDateString(), 'cash', null, null, 'c1');
    $before = ($this->bal)('cash');
    app(OwnerService::class)->allocateToBranch($this->branch->id, '5000', now()->toDateString());
    expect(($this->bal)('cash'))->toBe($before, 'consolidated cash grew by 5000 without any receipt');
    expect(($this->bal)('branch_capital'))->toBe(5000.0)->and(($this->bal)('owner_capital'))->toBe(0.0);
    $branchCash = (float) \App\Models\Erp\JournalLine::where('account_id', Account::where('role_key', 'cash')->value('id'))->where('branch_id', $this->branch->id)->selectRaw('SUM(debit-credit) s')->value('s');
    expect($branchCash)->toBe(5000.0);
});

it('REG ERP-007: an auto entry cannot be reversed by hand; repost and "post missing" restore a live, corrected entry', function () {
    $o = ($this->ship)(($this->place)());
    $e = JournalEntry::where('idempotency_key', SalesPoster::confirmKey($o))->first();
    $this->post('/admin/erp/journals/'.$e->id.'/reverse', ['reason' => 'x'])->assertSessionHasErrors('erp');
    expect($e->fresh()->status)->toBe('posted');
    // repost: reversed + versioned corrected entry
    app(\App\Services\Erp\Posting\DocumentPoster::class)->repost($e, null, 'fix');
    expect(JournalEntry::where('idempotency_key', SalesPoster::confirmKey($o).':v2')->where('status', 'posted')->exists())->toBeTrue()
        ->and(($this->bal)('receivable'))->toBe((float) $o->total)->and(($this->bal)('cogs'))->toBe(120.0);
    // service-level reversal (legacy data): post missing detects it and re-posts a new version
    app(JournalService::class)->reverse(JournalEntry::where('idempotency_key', SalesPoster::confirmKey($o).':v2')->first(), null, null, 'operator mistake');
    app(SalesPoster::class)->confirmed($o->refresh());                              // a plain replay never resurrects a reversed key
    expect(($this->bal)('receivable'))->toBe(0.0);
    $missing = app(BackfillService::class)->missing(true);
    expect($missing['orders']['created'])->toBe(1);
    expect(($this->bal)('receivable'))->toBe((float) $o->total, 'order is shipped but has no live revenue/receivable entry')->and(($this->bal)('cogs'))->toBe(120.0);
});

it('REG ERP-008: packaging cost is consumed from packaging supplies, never from Inventory', function () {
    $o = ($this->place)(1);
    $o->update(['packaging_fee' => '15.00', 'packaging_cost' => '4.00', 'total' => (string) ((float) $o->total + 15)]);
    $o = ($this->ship)($o);
    expect(($this->bal)('inventory'))->toBe(-(float) $o->cogs, 'inventory credited with packaging cost that no stock movement carries');
    expect(($this->bal)('packaging_supplies'))->toBe(-4.0)->and(($this->bal)('packaging'))->toBe(4.0);
});

it('REG ERP-009: a supplier debit note defaults to purchase expenses, never Inventory', function () {
    $s = Supplier::create(['name' => 'Sup QA']);
    app(PartyService::class)->opening('supplier', $s->id, '500', 'qa1');
    app(PartyService::class)->note('supplier', $s->id, 'debit_note', '100', 'qa2');
    expect(($this->bal)('inventory'))->toBe(0.0, 'inventory reduced in GL only')->and(($this->bal)('purchase_expenses'))->toBe(-100.0);
});
