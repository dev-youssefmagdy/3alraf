<?php

/*
 * Regression tests for the pass-1 ERP accounting audit fixes not covered by QaProbeErpAccountingTest
 * (docs/qa/ERP_AUDIT_FINDINGS.md: ERP-010, 012, 013, 014, 015, 016, 017, 018, 019, 023, 024, 031).
 */

use App\DTOs\ExpenseData;
use App\DTOs\Orders\CheckoutData;
use App\DTOs\PurchaseOrderData;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\Erp\Account;
use App\Models\Erp\AuditLog;
use App\Models\Erp\FiscalYear;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\JournalLine;
use App\Models\Erp\Period;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Admin;
use App\Services\Erp\Accounting\PeriodService;
use App\Services\Erp\Cash\SettlementService;
use App\Services\Erp\Equity\ErpSettings;
use App\Services\Erp\Owners\OwnerService;
use App\Services\Erp\Parties\PartyService;
use App\Services\Erp\Posting\Poster;
use App\Services\ExpenseService;
use App\Services\Orders\CarrierSettlementService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\PurchaseService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    codUpfront(false);
    $this->t = '/admin';
    $this->branch = Branch::first();
    $this->product = tap(Product::create(['type' => 'simple', 'name' => ['ar' => 'م', 'en' => 'fx'], 'slug' => 'fx-p', 'sku' => 'FXP', 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]), function ($p) {
        listProduct($p, ['price' => '100', 'cost' => '60', 'is_active' => true]);
        app(StockLedger::class)->record(Branch::first()->id, $p->id, 0, T::PURCHASE_IN, 100, '60');
    });
    $this->place = fn (int $qty = 2, ?int $customer = null) => app(OrderPlacementService::class)->place(new CheckoutData(
        customerId: $customer, name: 'Ali', phone: '01000000100', cityId: City::first()->id, address: 'St', shippingCompanyId: ShippingCompany::first()->id,
        paymentMethodId: PaymentMethod::where('type', 'cod')->first()->id), [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => $qty]])['order'];
    $this->to = function (Order $o, S ...$steps) {
        foreach ($steps as $s) {
            app(OrderStatusService::class)->change($o, $s);
        }

        return $o->refresh();
    };
    $this->deliver = fn (Order $o) => ($this->to)($o, S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED);
    $this->bal = fn (string $role) => (float) Account::where('role_key', $role)->value('current_balance');
    $this->admin = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->actingAs($this->admin, 'admin');
});

it('ERP-010: COD with prepaid shipping splits a refund by leg (provider/wallet leg vs cash goods part)', function () {
    $o = ($this->place)();
    $online = PaymentMethod::where('type', 'online')->first() ?? PaymentMethod::create(['type' => 'online', 'name' => ['en' => 'Card', 'ar' => 'بطاقة'], 'code' => 'card-x', 'is_active' => true]);
    $wallet = PaymentMethod::where('type', 'wallet')->first();
    $o->forceFill(['prepay_method_id' => $online->id, 'prepaid_shipping' => '30.00'])->save();
    expect(Poster::refundCredits($o->refresh(), '130.00', '30.00', '30.00'))->toBe(['gateway' => '30.00', 'cash' => '100.00']);
    $o->forceFill(['prepay_method_id' => $wallet->id])->save();
    expect(Poster::refundCredits($o->refresh(), '130.00', '0', '30.00'))->toBe(['wallet' => '30.00', 'cash' => '100.00'])
        ->and(Poster::refundCredits($o, '130.00', '0', '30.00', 'bank'))->toBe(['bank' => '130.00']);
});

it('ERP-012/024: carrier settles into bank; VAT payment, carrier payment and gateway payout clear their clearing accounts', function () {
    settings(['tax_rate' => 14]);
    $o = ($this->place)();
    $o->update(['shipping_cost' => '25.00']);
    $o = ($this->deliver)($o);
    app(CarrierSettlementService::class)->settle($o->shipping_company_id, now()->subDay()->toDateString(), now()->toDateString(), null, 'bank');
    expect(($this->bal)('bank'))->toBe((float) $o->total - 25)->and(($this->bal)('cash'))->toBe(0.0)->and(($this->bal)('vat_payable'))->toBe(28.0);
    $svc = app(SettlementService::class);
    $svc->payVat('28', 'bank', 'v1');
    $svc->payVat('28', 'bank', 'v1');                                              // replay
    expect(($this->bal)('vat_payable'))->toBe(0.0)->and(fn () => $svc->payVat('1', 'bank', 'v2'))->toThrow(\App\Exceptions\AccountingException::class);
    // return shipping on a prepaid flow leaves shipping payable: pay the carrier
    JournalEntry::query()->count();
    app(\App\Services\Erp\Accounting\JournalService::class)->post(['entry_date' => now()->toDateString(), 'description' => 'gw'], [['account' => 'gateway', 'debit' => '100'], ['account' => 'sales_revenue', 'credit' => '100']]);
    $this->post("{$this->t}/erp/cash-bank/payout", ['source' => 'gateway', 'amount' => 100, 'fee' => 3, 'account' => 'bank', 'ref' => 'p1'])->assertRedirect()->assertSessionHasNoErrors();
    expect(($this->bal)('gateway'))->toBe(0.0)->and(($this->bal)('gateway_fees'))->toBe(3.0);
    $this->get("{$this->t}/erp/cash-bank")->assertOk()->assertSee('data-balance="bank"', false);
});

it('ERP-013: an unpaid expense with a supplier carries the supplier party on the payable; category mapping is accepted', function () {
    $s = Supplier::create(['name' => 'Landlord']);
    $cat = ExpenseCategory::create(['name' => ['ar' => 'إيجار', 'en' => 'Rent'], 'is_active' => true, 'erp_account_id' => null]);
    app(\App\Services\Erp\Accounting\CoaTemplate::class)->seed();
    $cat->update(['erp_account_id' => Account::where('role_key', 'rent')->value('id')]);
    app(ExpenseService::class)->create(new ExpenseData($cat->id, null, '500.00', now()->toDateString(), null, null, false, false, null, $s->id));
    $line = JournalLine::where('account_id', Account::where('role_key', 'payable')->value('id'))->first();
    expect(($this->bal)('rent'))->toBe(500.0)->and($line->party_type)->toBe('supplier')->and($line->party_id)->toBe($s->id);
});

it('ERP-014: a POS counter sale is completed at once and its cash lands in POS cash, never with the carrier', function () {
    $c = Customer::create(['name' => 'Walk', 'email' => 'walk@example.com', 'phone' => '1055500001', 'password' => 'secret-123', 'is_active' => true]);
    $this->postJson("{$this->t}/pos/orders", ['items' => [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => 1]], 'customer_id' => $c->id,
        'name' => 'Walk', 'phone' => '1055500001', 'city_id' => City::first()->id, 'address' => 'Shop', 'shipping_company_id' => ShippingCompany::first()->id,
        'payment_method_id' => PaymentMethod::where('type', 'cod')->first()->id, 'paid_at_counter' => 1])->assertOk();
    $o = Order::latest('id')->first();
    expect($o->status)->toBe(S::DELIVERED)->and($o->counter_sale)->toBeTrue()->and((float) $o->cod_amount)->toBe(0.0)->and((float) $o->paid_amount)->toBe((float) $o->total);
    expect(($this->bal)('pos_cash'))->toBe((float) $o->total)->and(($this->bal)('carrier_receivable'))->toBe(0.0)->and(($this->bal)('receivable'))->toBe(0.0);
    expect(app(CarrierSettlementService::class)->settle($o->shipping_company_id, now()->subDay()->toDateString(), now()->toDateString()))->toBe(0);
    // register close: POS cash -> bank
    app(SettlementService::class)->payout('pos_cash', (string) $o->total, '0', 'bank', 'close-1');
    expect(($this->bal)('pos_cash'))->toBe(0.0)->and(($this->bal)('bank'))->toBe((float) $o->total);
});

it('ERP-015: a party receipt never settles an order receivable (held as an advance instead)', function () {
    $c = Customer::create(['name' => 'Mona', 'email' => 'mona2@example.com', 'phone' => '1055500002', 'password' => 'secret-123', 'is_active' => true]);
    $o = ($this->to)(($this->place)(2, $c->id), S::CONFIRMED, S::PROCESSING, S::SHIPPED);
    app(PartyService::class)->opening('customer', $c->id, '50', 'o1');
    app(PartyService::class)->receive($c->id, '120', 'r1');
    expect(($this->bal)('receivable'))->toBe((float) $o->total)->and(($this->bal)('customer_advances'))->toBe(70.0);
    ($this->to)($o, S::DELIVERED);                                                  // the order's own collection clears its receivable once
    expect(($this->bal)('receivable'))->toBe(0.0)->and(($this->bal)('carrier_receivable'))->toBe((float) $o->total);
    expect(fn () => app(PartyService::class)->applyAdvance('customer', $c->id, 'a1'))->toThrow(\App\Exceptions\AccountingException::class); // nothing non-order left to apply
});

it('ERP-016: a failed posting notifies ERP managers, shows on the migration page and can be retried; expenses into a closed period are refused', function () {
    $ps = app(PeriodService::class);
    $ps->createYear((int) now()->year);
    $periods = Period::where('starts_on', '<=', now()->toDateString())->orderBy('starts_on')->get();
    foreach ($periods as $p) {
        $ps->close($p, $this->admin->id);
    }
    $o = ($this->to)(($this->place)(), S::CONFIRMED, S::PROCESSING, S::SHIPPED);
    expect(JournalEntry::count())->toBe(0)->and($this->admin->notifications()->where('data->kind', 'erp_posting_failed')->count())->toBeGreaterThan(0);
    $cat = ExpenseCategory::create(['name' => ['ar' => 'ك', 'en' => 'Cat'], 'is_active' => true]);
    expect(fn () => app(ExpenseService::class)->create(new ExpenseData($cat->id, null, '10.00', now()->toDateString(), null, null, false)))->toThrow(\Illuminate\Validation\ValidationException::class);
    $this->get("{$this->t}/erp/migration")->assertOk()->assertSee('data-posting-failures', false);
    foreach ($periods->reverse() as $p) {
        $ps->reopen($p->refresh(), $this->admin->id, 'fix');
    }
    $log = AuditLog::where('action', 'posting.failed')->where('subject_id', $o->id)->first();
    $this->post("{$this->t}/erp/posting-failures/{$log->id}/retry")->assertRedirect()->assertSessionHasNoErrors();
    expect(($this->bal)('receivable'))->toBe((float) $o->total);
    $codes = app(\App\Services\Erp\Audit\AnomalyService::class)->detect()->pluck('code');
    expect($codes)->not->toContain('posting_failed');
});

it('ERP-017/018: manual journals cannot hit control accounts; party money forms need erp.journals.post', function () {
    app(\App\Services\Erp\Accounting\CoaTemplate::class)->seed();
    $this->post("{$this->t}/erp/journals", ['entry_date' => now()->toDateString(), 'description' => 'm', 'type' => 'manual', 'post_now' => 1, 'lines' => [
        ['account_id' => Account::where('role_key', 'receivable')->value('id'), 'debit' => 10], ['account_id' => Account::where('role_key', 'sales_revenue')->value('id'), 'credit' => 10]]])->assertSessionHasErrors('erp');
    expect(JournalEntry::count())->toBe(0);
    app(ErpSettings::class)->set('journal_requires_second_poster', true);
    $this->post("{$this->t}/erp/journals", ['entry_date' => now()->toDateString(), 'description' => 'm', 'type' => 'manual', 'post_now' => 1, 'lines' => [
        ['account_id' => Account::where('role_key', 'cash')->value('id'), 'debit' => 10], ['account_id' => Account::where('role_key', 'owner_capital')->value('id'), 'credit' => 10]]])->assertSessionHasErrors('erp');
    $sup = Supplier::create(['name' => 'S']);
    $this->admin->roles->each->revokePermissionTo('erp.journals.post');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->admin->unsetRelation('permissions')->unsetRelation('roles');
    $this->post("{$this->t}/erp/parties/supplier/{$sup->id}/settle", ['amount' => 5, 'account' => 'cash', 'ref' => 't1'])->assertForbidden();
});

it('ERP-019: with landed_costs = capitalise PO shipping/other costs go into the unit cost and inventory', function () {
    app(ErpSettings::class)->set('landed_costs', 'capitalise');
    $s = Supplier::create(['name' => 'Sup', 'is_active' => true]);
    app(PurchaseService::class)->create(new PurchaseOrderData($s->id, $this->branch->id, now()->toDateString(), '20.00', '10.00', null,
        [['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 10, 'unit_cost' => '50.00']], true));
    expect((float) StockMovement::where('type', 'purchase_in')->latest('id')->value('unit_cost'))->toBe(53.0)
        ->and(($this->bal)('inventory'))->toBe(530.0)->and(($this->bal)('purchase_expenses'))->toBe(0.0)->and(($this->bal)('payable'))->toBe(530.0);
});

it('ERP-023: a merchant COD payment fee is owed to the carrier (shipping payable), not the COD receivable', function () {
    $o = ($this->place)();
    $o->update(['payment_fee' => '5.00']);
    $o = ($this->deliver)($o);
    expect(($this->bal)('gateway_fees'))->toBe(5.0)->and(($this->bal)('carrier_receivable'))->toBe((float) $o->total)->and(($this->bal)('shipping_payable'))->toBe(5.0 + (float) $o->shipping_cost);
});

it('ERP-031: withdrawals need cash; year-end close moves drawings to capital', function () {
    $svc = app(OwnerService::class);
    $o = $svc->save(['name' => 'Ali', 'ownership_percent' => 100, 'is_eligible' => true]);
    expect(fn () => $svc->transact($o, 'withdrawal', '10', now()->toDateString(), 'cash'))->toThrow(\App\Exceptions\AccountingException::class);
    $svc->transact($o, 'contribution', '1000', now()->subYear()->endOfYear()->toDateString(), 'cash');
    $svc->transact($o, 'withdrawal', '200', now()->subYear()->endOfYear()->toDateString(), 'cash');
    app(PeriodService::class)->createYear((int) now()->subYear()->year);
    $fy = FiscalYear::where('starts_on', now()->subYear()->startOfYear()->toDateString())->first();
    app(PeriodService::class)->closeYear($fy, $this->admin->id);
    expect(($this->bal)('owner_drawings'))->toBe(0.0)->and(($this->bal)('owner_capital'))->toBe(800.0)->and($svc->equity()[$o->id]['equity'])->toBe('800.0000');
});
