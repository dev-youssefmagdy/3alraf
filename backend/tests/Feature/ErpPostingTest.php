<?php

use App\DTOs\ExpenseData;
use App\DTOs\Orders\CheckoutData;
use App\DTOs\Orders\ReturnRequestData;
use App\DTOs\PurchaseOrderData;
use App\DTOs\StockAdjustmentData;
use App\DTOs\StockTransferData;
use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Events\OrderStatusChanged;
use App\Models\Branch;
use App\Models\City;
use App\Models\DiscountCode;
use App\Models\Erp\Account;
use App\Models\Erp\AuditLog;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\JournalLine;
use App\Models\Erp\Period;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use App\Models\Admin;
use App\Services\ExpenseService;
use App\Services\Erp\Accounting\PeriodService;
use App\Services\Erp\Posting\PurchasePoster;
use App\Services\Erp\Reports\LedgerReports;
use App\Services\Orders\CarrierSettlementService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\ReturnService;
use App\Services\PurchaseService;
use App\Services\StockAdjustmentService;
use App\Services\StockTransferService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    codUpfront(false);   // carrier collects the full total
    $this->t = '/admin';
    $this->branch = Branch::first();
    $mk = fn (string $slug, string $price, string $cost) => tap(Product::create(['type' => 'simple', 'name' => ['ar' => 'م', 'en' => $slug], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]), function ($p) use ($price, $cost) {
        listProduct($p, ['price' => $price, 'cost' => $cost, 'is_active' => true]);
        app(StockLedger::class)->record(Branch::first()->id, $p->id, 0, T::PURCHASE_IN, 100, $cost);
    });
    $this->product = $mk('pa', '100', '60');
    $this->product2 = $mk('pb', '50', '30');
    // purchase_in above is raw stock set-up (no PO), so the ledger inventory starts at 0; business documents below drive it
    $this->place = function (array $lines = null, array $o = []) {
        $lines ??= [[$this->product->id, 2]];

        return app(OrderPlacementService::class)->place(new CheckoutData(
            name: 'Ali', phone: '01000000100', cityId: City::first()->id, address: 'St', shippingCompanyId: ShippingCompany::first()->id,
            paymentMethodId: PaymentMethod::where('type', 'cod')->first()->id, discountCode: $o['discountCode'] ?? null),
            array_map(fn ($l) => ['product_id' => $l[0], 'variant_id' => 0, 'qty' => $l[1]], $lines))['order'];
    };
    $this->to = function (Order $o, S ...$steps) {
        foreach ($steps as $s) {
            app(OrderStatusService::class)->change($o, $s);
        }

        return $o->refresh();
    };
    $this->deliver = fn (Order $o) => ($this->to)($o, S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED);
    $this->ship = fn (Order $o) => ($this->to)($o, S::CONFIRMED, S::PROCESSING, S::SHIPPED);   // revenue recognition point (default policy)
    $this->bal = fn (string $role) => (string) Account::where('role_key', $role)->value('current_balance');
    $this->reports = fn () => app(LedgerReports::class);
    $this->admin = Admin::first();
    $this->actingAs($this->admin, 'admin');
});

/** Every posted entry is balanced and the trial balance agrees. */
function assertLedgerBalanced(): void
{
    foreach (JournalEntry::with('lines')->get() as $e) {
        expect(round((float) $e->lines->sum('debit'), 4))->toBe(round((float) $e->lines->sum('credit'), 4), $e->number.' is unbalanced');
    }
    expect(app(LedgerReports::class)->trialBalance()['balanced'])->toBeTrue();
}

function netProfitCard($test): string
{
    $d = $test->get("{$test->t}/reports/net-profit")->assertOk()->viewData('data');

    return collect($d['cards'])->firstWhere('label', __('reports.card.net_profit'))['value'];
}

function ledgerNet(): string
{
    return number_format((float) app(LedgerReports::class)->profitLoss(null, null)['net_profit'], 2);
}

it('posts sale + COGS at shipment (default recognition) and the payment on delivery, all balanced', function () {
    $o = ($this->place)();
    expect(JournalEntry::count())->toBe(0);                        // pending: nothing
    ($this->to)($o, S::CONFIRMED, S::PROCESSING);
    expect(JournalEntry::count())->toBe(0);                        // confirmed but not shipped: control has not passed, nothing recognised
    ($this->to)($o, S::SHIPPED);
    $o->refresh();
    expect(JournalEntry::count())->toBe(1);
    expect((float) ($this->bal)('receivable'))->toBe((float) $o->total)
        ->and((float) ($this->bal)('sales_revenue'))->toBe((float) $o->revenueGoods())
        ->and((float) ($this->bal)('cogs'))->toBe(120.0)
        ->and((float) ($this->bal)('inventory'))->toBe(-120.0)       // no purchase document booked it in; = value of the sale_out movements
        ->and((float) ($this->bal)('shipping_revenue'))->toBe((float) $o->shipping_net);
    expect(JournalLine::whereNotNull('branch_id')->count())->toBe(JournalLine::count());   // branch on every line
    ($this->to)($o, S::DELIVERED);
    $o->refresh();
    expect((float) ($this->bal)('receivable'))->toBe(0.0)
        ->and((float) ($this->bal)('carrier_receivable'))->toBe((float) $o->total);   // COD cash held by the carrier
    expect(ledgerNet())->toBe(number_format((float) $o->net_profit, 2));
    assertLedgerBalanced();
});

it('handles partial payments by cumulative amount and never double posts a replay', function () {
    $o = ($this->ship)(($this->place)());
    $poster = app(\App\Services\Erp\Posting\PaymentPoster::class);
    $o->update(['paid_amount' => '50.00']);
    $poster->sync($o->refresh());
    $poster->sync($o->refresh());                                   // replay
    expect((float) ($this->bal)('receivable'))->toBe((float) $o->total - 50)->and((float) ($this->bal)('carrier_receivable'))->toBe(50.0);
    $o->update(['paid_amount' => $o->total]);
    $poster->sync($o->refresh());
    expect((float) ($this->bal)('receivable'))->toBe(0.0)->and((float) ($this->bal)('carrier_receivable'))->toBe((float) $o->total);
    expect(JournalEntry::where('idempotency_key', 'like', 'order:'.$o->id.':pay:%')->count())->toBe(2);
    assertLedgerBalanced();
});

it('keeps revenue net of the discount', function () {
    DiscountCode::create(['code' => 'ALL10', 'type' => 'percent', 'value' => 10, 'scope' => 'all', 'is_active' => true, 'min_order' => 0]);
    $o = ($this->ship)(($this->place)(null, ['discountCode' => 'ALL10']));
    expect((float) $o->discount)->toBe(20.0)
        ->and((float) ($this->bal)('sales_revenue'))->toBe(180.0)
        ->and((float) ($this->bal)('receivable'))->toBe((float) $o->total);
    expect(ledgerNet())->toBe(number_format((float) $o->net_profit, 2));
    assertLedgerBalanced();
});

it('books VAT as a liability, never revenue (exclusive and inclusive prices)', function () {
    settings(['tax_rate' => 14]);
    $o = ($this->ship)(($this->place)());
    expect((float) $o->tax)->toBe(28.0)->and((float) ($this->bal)('vat_payable'))->toBe(28.0)->and((float) ($this->bal)('sales_revenue'))->toBe(200.0)
        ->and((float) ($this->bal)('receivable'))->toBe((float) $o->total);
    expect(ledgerNet())->toBe(number_format((float) $o->net_profit, 2));

    settings(['prices_include_tax' => true]);
    $i = ($this->ship)(($this->place)());
    expect($i->taxIncluded())->toBeTrue();
    $entry = JournalEntry::where('idempotency_key', 'order:'.$i->id.':confirm')->first();
    $rev = (float) $entry->lines()->where('account_id', Account::where('role_key', 'sales_revenue')->value('id'))->sum('credit');
    expect($rev)->toBe((float) $i->revenueGoods())->and($rev)->toBeLessThan(200.0);
    expect(ledgerNet())->toBe(number_format((float) $o->net_profit + (float) $i->net_profit, 2));
    assertLedgerBalanced();
});

it('books shipping revenue and cost, gateway fee, packaging and affiliate commission like the profit formula', function () {
    $o = ($this->place)();
    $o->update(['shipping_net' => '30.00', 'shipping_cost' => '25.00', 'payment_fee' => '7.50', 'payment_fee_charged' => '7.50', 'cod_fee' => '5.00',
        'affiliate_commission' => '11.50', 'packaging_fee' => '15.00', 'packaging_cost' => '4.00', 'total' => '200.00' + 30 + 7.5 + 5 + 15]);
    $o = ($this->ship)($o);
    expect((float) ($this->bal)('shipping_revenue'))->toBe(30.0)->and((float) ($this->bal)('shipping_expense'))->toBe(25.0)->and((float) ($this->bal)('gateway_fees'))->toBe(7.5)
        ->and((float) ($this->bal)('fee_income'))->toBe(12.5)->and((float) ($this->bal)('affiliate_expense'))->toBe(11.5)->and((float) ($this->bal)('packaging'))->toBe(4.0)
        ->and((float) ($this->bal)('other_revenue'))->toBe(15.0)->and((float) ($this->bal)('receivable'))->toBe(257.5);
    expect(ledgerNet())->toBe(number_format((float) $o->net_profit, 2));
    assertLedgerBalanced();
});

it('posts partial and full returns as contra-revenue with inventory/COGS reversal and follows the net profit report', function () {
    $o = ($this->deliver)(($this->place)());
    $item = $o->items()->first();
    $svc = app(ReturnService::class);
    $r1 = $svc->approve($svc->request($o, new ReturnRequestData(quantities: [$item->id => 1], reason: 'x', restock: true, refundShipping: false, returnShippingCost: '5.00')));
    expect((float) ($this->bal)('sales_returns'))->toBe(-100.0)->and((float) ($this->bal)('sales_revenue'))->toBe(200.0)   // contra, revenue untouched
        ->and((float) ($this->bal)('cogs'))->toBe(60.0)->and((float) ($this->bal)('shipping_expense'))->toBe((float) $o->shipping_cost + 5);
    expect(ledgerNet())->toBe(netProfitCard($this));
    // second (final) return -> order becomes returned
    $r2 = $svc->approve($svc->request($o->refresh(), new ReturnRequestData(quantities: [$item->id => 1], reason: 'x', restock: true, refundShipping: false, returnShippingCost: '0')));
    expect($o->refresh()->status)->toBe(S::RETURNED)->and((float) ($this->bal)('sales_returns'))->toBe(-200.0)->and((float) ($this->bal)('cogs'))->toBe(0.0);
    assertLedgerBalanced();
});

it('books a damaged return as inventory loss instead of restocking', function () {
    $o = ($this->deliver)(($this->place)());
    $item = $o->items()->first();
    $svc = app(ReturnService::class);
    $svc->approve($svc->request($o, new ReturnRequestData(quantities: [$item->id => 1], reason: 'broken', restock: false, refundShipping: false, returnShippingCost: '0')));
    expect((float) ($this->bal)('inventory_loss'))->toBe(60.0)->and((float) ($this->bal)('cogs'))->toBe(60.0)->and((float) ($this->bal)('inventory'))->toBe(-120.0);   // goods never came back to stock
    assertLedgerBalanced();
});

it('posts the refund cash leg and clears the receivable', function () {
    $o = ($this->deliver)(($this->place)());
    $item = $o->items()->first();
    $svc = app(ReturnService::class);
    $r = $svc->approve($svc->request($o, new ReturnRequestData(quantities: [$item->id => 1], reason: 'x', restock: true, refundShipping: false, returnShippingCost: '0')));
    expect((float) ($this->bal)('receivable'))->toBe(-100.0 + (float) $o->total - (float) $o->total);   // paid in full on delivery, return credits the customer
    $svc->refund($r);
    // ERP-001: the merchant hands the cash back; the carrier still owes the full COD amount it collected
    expect((float) ($this->bal)('receivable'))->toBe(0.0)->and((float) ($this->bal)('carrier_receivable'))->toBe((float) $o->total)->and((float) ($this->bal)('cash'))->toBe(-100.0);
    assertLedgerBalanced();
});

it('refunding shipping with a return reverses shipping revenue', function () {
    $o = ($this->place)();
    $o->update(['shipping_net' => '30.00', 'total' => '230.00']);
    $o = ($this->deliver)($o);
    $item = $o->items()->first();
    $svc = app(ReturnService::class);
    $r = $svc->approve($svc->request($o, new ReturnRequestData(quantities: [$item->id => 1], reason: 'x', restock: true, refundShipping: true, returnShippingCost: '0')));
    $svc->refund($r);
    expect((float) ($this->bal)('shipping_revenue'))->toBe(0.0)->and((float) ($this->bal)('receivable'))->toBe(0.0);
    assertLedgerBalanced();
});

it('cancel before recognition posts nothing, cancel after shipment (returned to sender) reverses the recognition', function () {
    $a = ($this->to)(($this->place)(), S::CANCELLED);
    expect(JournalEntry::count())->toBe(0);

    $c = ($this->to)(($this->place)(), S::CONFIRMED);                    // confirmed, not shipped: nothing recognised, cancel posts nothing
    ($this->to)($c, S::CANCELLED);
    expect(JournalEntry::count())->toBe(0);

    $b = ($this->ship)(($this->place)());
    expect(JournalEntry::where('status', 'posted')->count())->toBe(1);
    ($this->to)($b, S::CANCELLED);                                       // returned to sender after shipment: restocked + reversed
    expect(JournalEntry::where('type', 'reversal')->count())->toBe(1)->and(JournalEntry::where('status', 'reversed')->count())->toBe(1);
    foreach (['receivable', 'sales_revenue', 'cogs', 'inventory', 'shipping_revenue'] as $role) {
        expect((float) ($this->bal)($role))->toBe(0.0);
    }
    expect(ledgerNet())->toBe('0.00');
    assertLedgerBalanced();
});

it('cancel of a paid wallet-style order refunds the money in the ledger', function () {
    $o = ($this->ship)(($this->place)());
    $o->update(['paid_amount' => $o->total]);
    app(\App\Services\Erp\Posting\PaymentPoster::class)->sync($o->refresh());
    app(OrderStatusService::class)->change($o, S::CANCELLED);
    // the COD money sits with the carrier (receivable), the refund is paid by the merchant from cash (ERP-001), never credited to the carrier
    expect((float) ($this->bal)('receivable'))->toBe(0.0)->and((float) ($this->bal)('carrier_receivable'))->toBe((float) $o->total)->and((float) ($this->bal)('cash'))->toBe(-(float) $o->total);
    assertLedgerBalanced();
});

it('posts purchase receipt to inventory and supplier payable, landed costs to purchase expenses, and supplier payments', function () {
    $s = Supplier::create(['name' => 'Sup', 'is_active' => true]);
    $po = app(PurchaseService::class)->create(new PurchaseOrderData($s->id, $this->branch->id, now()->toDateString(), '20.00', '10.00', null,
        [['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 10, 'unit_cost' => '50.00']], true));
    expect((float) ($this->bal)('inventory'))->toBe(500.0)->and((float) ($this->bal)('purchase_expenses'))->toBe(30.0)->and((float) ($this->bal)('payable'))->toBe(530.0);
    $line = JournalLine::where('account_id', Account::where('role_key', 'payable')->value('id'))->first();
    expect($line->party_type)->toBe('supplier')->and($line->party_id)->toBe($s->id);
    $pp = app(PurchasePoster::class);
    $pp->payment($po, '200.00', 'p1');
    $pp->payment($po, '200.00', 'p1');                                 // replay
    expect((float) ($this->bal)('payable'))->toBe(330.0)->and((float) ($this->bal)('cash'))->toBe(-200.0);
    // cancelling a draft books nothing
    $draft = app(PurchaseService::class)->create(new PurchaseOrderData($s->id, $this->branch->id, now()->toDateString(), '0', '0', null, [['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 1, 'unit_cost' => '5.00']], false));
    $before = JournalEntry::count();
    app(PurchaseService::class)->cancel($draft);
    expect(JournalEntry::count())->toBe($before);
    assertLedgerBalanced();
});

it('posts stock adjustments to inventory loss / gain', function () {
    $svc = app(StockAdjustmentService::class);
    $item = fn (int $q) => [['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => $q, 'unit_cost' => '60.00']];
    $svc->create(new StockAdjustmentData($this->branch->id, AdjustmentType::DECREASE, AdjustmentReason::DAMAGED, null, $item(3)));
    expect((float) ($this->bal)('inventory_loss'))->toBe(180.0)->and((float) ($this->bal)('inventory'))->toBe(-180.0);
    $svc->create(new StockAdjustmentData($this->branch->id, AdjustmentType::INCREASE, AdjustmentReason::FOUND, null, $item(1)));
    expect((float) ($this->bal)('inventory_loss'))->toBe(120.0);
    expect(ledgerNet())->toBe('-120.00');
    assertLedgerBalanced();
});

it('keeps consolidated profit and inventory neutral across a branch transfer', function () {
    $b2 = Branch::create(['name' => ['ar' => 'فرع 2'], 'is_active' => true, 'is_default' => false]);
    $svc = app(StockTransferService::class);
    $t = $svc->create(new StockTransferData($this->branch->id, $b2->id, null, [['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 5]]));
    $svc->send($t);
    $inv = fn () => (float) ($this->bal)('inventory') + (float) ($this->bal)('inventory_transit');
    expect($inv())->toBe(0.0)->and((float) ($this->bal)('inventory_transit'))->toBe(300.0);
    $svc->receive($t->refresh());
    expect($inv())->toBe(0.0)->and((float) ($this->bal)('inventory_transit'))->toBe(0.0)->and(ledgerNet())->toBe('0.00');
    expect((float) JournalLine::where('branch_id', $b2->id)->sum('debit'))->toBe(300.0);
    assertLedgerBalanced();
});

it('posts expenses paid and unpaid, honours category mapping, edits and deletes', function () {
    $cat = ExpenseCategory::first() ?? ExpenseCategory::create(['name' => ['ar' => 'ك', 'en' => 'Cat'], 'is_active' => true]);
    $svc = app(ExpenseService::class);
    $e1 = $svc->create(new ExpenseData($cat->id, null, '100.00', now()->toDateString(), 'rent', null, false));            // legacy: unmapped, paid
    expect((float) ($this->bal)('other_expense'))->toBe(100.0)->and((float) ($this->bal)('cash'))->toBe(-100.0);
    $rent = Account::where('role_key', 'rent')->first();
    $cat->update(['erp_account_id' => $rent->id]);
    $e2 = $svc->create(new ExpenseData($cat->id, $this->branch->id, '40.00', now()->toDateString(), null, null, false, false));   // unpaid -> payable
    expect((float) ($this->bal)('rent'))->toBe(40.0)->and((float) ($this->bal)('payable'))->toBe(40.0);
    $svc->update($e2, new ExpenseData($cat->id, $this->branch->id, '60.00', now()->toDateString(), null, null, false, false));
    expect((float) ($this->bal)('rent'))->toBe(60.0)->and((float) ($this->bal)('payable'))->toBe(60.0);
    $svc->delete($e1);
    expect((float) ($this->bal)('other_expense'))->toBe(0.0)->and((float) ($this->bal)('cash'))->toBe(0.0);
    expect(ledgerNet())->toBe('-60.00');
    assertLedgerBalanced();
});

it('never double posts when an event is replayed', function () {
    $o = ($this->deliver)(($this->place)());
    $n = JournalEntry::count();
    $before = Account::pluck('current_balance', 'id');
    foreach (range(1, 3) as $_) {
        event(new OrderStatusChanged($o->refresh(), S::PENDING, S::CONFIRMED));
        event(new OrderStatusChanged($o->refresh(), S::SHIPPED, S::DELIVERED));
        event(new \App\Events\OrderPaymentUpdated($o->refresh(), 'paid'));
    }
    expect(JournalEntry::count())->toBe($n)->and(Account::pluck('current_balance', 'id'))->toEqual($before);
});

it('settles a carrier: cash minus carrier fee, receivable cleared', function () {
    $o = ($this->place)();
    $o->update(['shipping_cost' => '25.00']);
    $o = ($this->deliver)($o);
    app(CarrierSettlementService::class)->settle($o->shipping_company_id, now()->subDay()->toDateString(), now()->toDateString());
    expect((float) ($this->bal)('carrier_receivable'))->toBe(0.0)->and((float) ($this->bal)('cash'))->toBe((float) $o->total - 25)->and((float) ($this->bal)('shipping_payable'))->toBe(0.0);
    assertLedgerBalanced();
});

it('logs a closed-period posting failure without breaking the business flow, and re-posts idempotently after reopening', function () {
    $ps = app(PeriodService::class);
    $ps->createYear((int) now()->year);
    $periods = Period::where('starts_on', '<=', now()->toDateString())->orderBy('starts_on')->get();
    foreach ($periods as $p) {
        $ps->close($p, $this->admin->id);
    }
    $o = ($this->ship)(($this->place)());                                // business op still succeeds
    expect($o->status)->toBe(S::SHIPPED)->and(JournalEntry::count())->toBe(0);
    $log = AuditLog::where('action', 'posting.failed')->first();
    expect($log)->not->toBeNull()->and($log->subject_id)->toBe($o->id)->and($log->meta['error'])->toContain('closed');
    foreach ($periods->reverse() as $p) {
        $ps->reopen($p->refresh(), $this->admin->id, 'fix');
    }
    app(\App\Services\Erp\Posting\SalesPoster::class)->confirmed($o);
    app(\App\Services\Erp\Posting\SalesPoster::class)->confirmed($o);
    expect(JournalEntry::count())->toBe(1);
});

it('lazily seeds the chart of accounts when none exists yet', function () {
    Account::query()->delete();
    $o = ($this->ship)(($this->place)());
    expect(Account::count())->toBeGreaterThan(40)->and(JournalEntry::count())->toBe(1);
});

it('matches the existing Net Profit report on the worked example (discount, shipping, fee, commission, return, expense)', function () {
    DiscountCode::create(['code' => 'W10', 'type' => 'percent', 'value' => 10, 'scope' => 'all', 'max_discount' => 20, 'is_active' => true, 'min_order' => 100]);
    $o = ($this->place)([[$this->product->id, 2], [$this->product2->id, 1]], ['discountCode' => 'W10']);
    expect((float) $o->subtotal)->toBe(250.0)->and((float) $o->discount)->toBe(20.0)->and((float) $o->cogs)->toBe(150.0);
    $o->update(['shipping_net' => '30.00', 'shipping_cost' => '25.00', 'payment_fee' => '7.50', 'payment_fee_charged' => '7.50', 'affiliate_commission' => '11.50', 'total' => '267.50']);
    $o = ($this->deliver)($o);
    expect((float) $o->net_profit)->toBe(73.5);
    expect(ledgerNet())->toBe('73.50')->and(netProfitCard($this))->toBe('73.50');
    // + an expense and a partial return (1 x 100 line, restocked, 5 return shipping)
    $cat = ExpenseCategory::first() ?? ExpenseCategory::create(['name' => ['ar' => 'ك', 'en' => 'Cat'], 'is_active' => true]);
    app(ExpenseService::class)->create(new ExpenseData($cat->id, null, '10.00', now()->toDateString(), null, null, false));
    $item = $o->items()->where('product_id', $this->product->id)->first();
    $svc = app(ReturnService::class);
    $svc->approve($svc->request($o, new ReturnRequestData(quantities: [$item->id => 1], reason: 'x', restock: true, refundShipping: false, returnShippingCost: '5.00')));
    expect(ledgerNet())->toBe(netProfitCard($this));
    assertLedgerBalanced();
});
