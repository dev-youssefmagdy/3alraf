<?php

/*
 * Final QA sign-off (docs/qa/QA_SIGNOFF.md): independent verification of the pass-1/pass-2 ERP fixes and adjacent edge cases.
 * 'VERIFY …'      = a fix confirmed (incl. edge cases the fix tests did not cover).
 * 'QA-BUG-1xx …'  = a defect found during sign-off, asserting the CORRECT behaviour.
 */

use App\DTOs\ExpenseData;
use App\DTOs\Orders\CheckoutData;
use App\DTOs\Orders\ReturnRequestData;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Exceptions\AccountingException;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\JournalLine;
use App\Models\Erp\Period;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Admin;
use App\Services\Affiliates\AffiliateService;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\PeriodService;
use App\Services\Erp\Equity\ErpSettings;
use App\Services\Erp\Posting\DocumentPoster;
use App\Services\Erp\Posting\Poster;
use App\Services\Erp\Posting\SalesPoster;
use App\Services\ExpenseService;
use App\Services\Orders\CarrierSettlementService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\ReturnService;
use App\Services\Stock\StockLedger;
use App\Support\ErpRules;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    codUpfront(false);
    $this->t = '/admin';
    $this->branch = Branch::first();
    $this->product = tap(Product::create(['type' => 'simple', 'name' => ['ar' => 'م', 'en' => 'so'], 'slug' => 'so-p', 'sku' => 'SOP', 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]), function ($p) {
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
    $this->ship = fn (Order $o) => ($this->to)($o, S::CONFIRMED, S::PROCESSING, S::SHIPPED);
    $this->deliver = fn (Order $o) => ($this->to)($o, S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED);
    $this->bal = fn (string $role) => (float) Account::where('role_key', $role)->value('current_balance');
    $this->returnAll = function (Order $o, int $qty = 1) {
        $rs = app(ReturnService::class);

        return $rs->approve($rs->request($o->refresh(), new ReturnRequestData([$o->items()->first()->id => $qty], 'x', true, false)));
    };
    $this->closeThrough = function (string $date) {
        $ps = app(PeriodService::class);
        $ps->createYear((int) substr($date, 0, 4));
        foreach (Period::where('starts_on', '<=', $date)->where('status', 'open')->orderBy('starts_on')->get() as $p) {
            $ps->close($p, Admin::first()->id);
        }
    };
    $this->admin = Admin::first();
    $this->actingAs($this->admin, 'admin');
});

// ---------------------------------------------------------------- ERP-001 / 010: refund "paid from" variations
it('VERIFY ERP-001: COD refund before carrier settlement defaults to cash; refund_from=bank or carrier_receivable is honoured; a bogus role is refused', function () {
    $o = ($this->deliver)(($this->place)(3));                                      // delivered, carrier NOT yet settled
    $carrier = ($this->bal)('carrier_receivable');
    expect($carrier)->toBe((float) $o->total);
    $rs = app(ReturnService::class);

    $r1 = ($this->returnAll)($o);
    $rs->refund($r1);                                                               // default: merchant pays cash back
    expect(($this->bal)('cash'))->toBe(-100.0)->and(($this->bal)('carrier_receivable'))->toBe($carrier);

    $r2 = ($this->returnAll)($o);
    $rs->refund($r2, null, 'bank');
    expect(($this->bal)('bank'))->toBe(-100.0)->and(($this->bal)('carrier_receivable'))->toBe($carrier);

    $r3 = ($this->returnAll)($o);
    expect(fn () => $rs->refund($r3, null, 'receivable'))->toThrow(ValidationException::class); // not a money account
    $rs->refund($r3->refresh(), null, 'carrier_receivable');                       // explicit: the carrier refunded before settling
    expect(($this->bal)('carrier_receivable'))->toBe($carrier - 100)->and(($this->bal)('receivable'))->toBe(0.0);
    // HTTP form rejects a bogus paid-from account too
    $o2 = ($this->deliver)(($this->place)(1));
    $r4 = ($this->returnAll)($o2);
    $this->postJson("{$this->t}/returns/{$r4->id}/refund", ['refund_from' => 'sales_revenue'])->assertStatus(422);
});

it('VERIFY ERP-014: POS counter sale + return refunds out of POS cash, carrier untouched', function () {
    $c = Customer::create(['name' => 'Walk', 'email' => 'walk-so@example.com', 'phone' => '1055500091', 'password' => 'secret-123', 'is_active' => true]);
    $this->postJson("{$this->t}/pos/orders", ['items' => [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => 2]], 'customer_id' => $c->id,
        'name' => 'Walk', 'phone' => '1055500091', 'city_id' => City::first()->id, 'address' => 'Shop', 'shipping_company_id' => ShippingCompany::first()->id,
        'payment_method_id' => PaymentMethod::where('type', 'cod')->first()->id, 'paid_at_counter' => 1])->assertOk();
    $o = Order::latest('id')->first();
    $pos = ($this->bal)('pos_cash');
    expect($o->status)->toBe(S::DELIVERED)->and($pos)->toBe((float) $o->total);
    $r = ($this->returnAll)($o);
    app(ReturnService::class)->refund($r);
    foreach (['pos_cash' => $pos - 100, 'carrier_receivable' => 0.0, 'cash' => 0.0, 'receivable' => 0.0, 'sales_returns' => -100.0] as $role => $want) {
        expect(($this->bal)($role))->toBe($want, $role);
    }
});

// ---------------------------------------------------------------- ERP-003: affiliate payout paid twice
it('VERIFY ERP-003: an affiliate payout cannot be paid twice; a replay/repost of the payout posts nothing more', function () {
    $a = Affiliate::create(['name' => 'Aff', 'email' => 'aff-so@egy.test', 'password' => 'password', 'status' => 'approved', 'approved_at' => now()]);
    $o = ($this->place)();
    $o->update(['affiliate_id' => $a->id, 'affiliate_commission' => '10.00']);
    $o = ($this->deliver)($o);
    AffiliateCommission::create(['affiliate_id' => $a->id, 'order_id' => $o->id, 'base' => '200', 'percent' => '5', 'amount' => '10.00', 'status' => 'approved', 'approved_at' => now()]);
    $svc = app(AffiliateService::class);
    $p = $svc->markPaid($svc->requestPayout($a), null, 'bank');
    expect(fn () => $svc->markPaid($p->refresh(), null, 'cash'))->toThrow(ValidationException::class);
    app(DocumentPoster::class)->post('affiliate_payout', $p->id);                  // "post missing"/retry replay
    expect(($this->bal)('affiliate_payable'))->toBe(0.0)->and(($this->bal)('bank'))->toBe(-10.0)->and(($this->bal)('cash'))->toBe(0.0)
        ->and(JournalEntry::where('source_type', 'affiliate_payout')->count())->toBe(1);
    expect(fn () => $svc->markPaid($p->refresh(), null, 'receivable'))->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------- ERP-011: policy switch mid-life + RTS
it('VERIFY ERP-011: switching revenue_recognition mid-life never double-recognises nor strands goods-out-for-delivery', function () {
    $s = app(ErpSettings::class);
    $a = ($this->ship)(($this->place)());                                           // shipped policy: recognised at shipment
    $s->set('revenue_recognition', 'delivered');
    $b = ($this->ship)(($this->place)());                                           // delivered policy: parked
    expect(($this->bal)('goods_in_delivery'))->toBe(120.0)->and(($this->bal)('sales_revenue'))->toBe(200.0);
    ($this->to)($a, S::DELIVERED);                                                  // already recognised: nothing new
    expect(($this->bal)('sales_revenue'))->toBe(200.0)->and(($this->bal)('goods_in_delivery'))->toBe(120.0);
    $s->set('revenue_recognition', 'shipped');
    ($this->to)($b, S::DELIVERED);                                                  // parked order recognised once, from goods out for delivery
    expect(($this->bal)('sales_revenue'))->toBe(400.0)->and(($this->bal)('goods_in_delivery'))->toBe(0.0)->and(($this->bal)('cogs'))->toBe(240.0)
        ->and(($this->bal)('inventory'))->toBe(-240.0);
    $s->set('revenue_recognition', 'confirmed');
    $c = ($this->to)(($this->place)(), S::CONFIRMED);
    $s->set('revenue_recognition', 'delivered');
    ($this->to)($c, S::PROCESSING, S::SHIPPED, S::DELIVERED);                       // recognised at confirm, never again
    expect(($this->bal)('sales_revenue'))->toBe(600.0)->and(($this->bal)('cogs'))->toBe(360.0)->and(($this->bal)('goods_in_delivery'))->toBe(0.0);
    expect(app(\App\Services\Erp\Reconciliation\LedgerVerifier::class)->run()['ok'])->toBeTrue();
});

it('VERIFY ERP-011: RTS under the "delivered" policy restocks and clears goods out for delivery; a delivered (partially returned) order cannot be RTS-cancelled', function () {
    app(ErpSettings::class)->set('revenue_recognition', 'delivered');
    $o = ($this->ship)(($this->place)());
    $o = ($this->to)($o, S::CANCELLED);
    foreach (['goods_in_delivery', 'inventory', 'sales_revenue', 'receivable', 'cogs'] as $r) {
        expect(($this->bal)($r))->toBe(0.0, $r);
    }
    expect(app(StockLedger::class)->onHand($this->branch->id, $this->product->id))->toBe(100);
    $d = ($this->deliver)(($this->place)());
    ($this->returnAll)($d);                                                         // partial return
    expect(fn () => app(OrderStatusService::class)->change($d->refresh(), S::CANCELLED))->toThrow(ValidationException::class);
    expect($d->refresh()->status)->toBe(S::DELIVERED);
});

// ---------------------------------------------------------------- ERP-007: repost after a period close
it('VERIFY ERP-007: repost of an entry in a closed period reverses and re-posts in the open current period; repost into a closed current period is refused atomically', function () {
    $past = now()->subMonthNoOverflow()->startOfMonth()->addDays(9)->toDateString();
    $o = Poster::withDate($past, fn () => ($this->ship)(($this->place)()));
    $e = JournalEntry::where('idempotency_key', SalesPoster::confirmKey($o))->first();
    expect($e->entry_date->toDateString())->toBe($past);
    ($this->closeThrough)($past);
    expect($e->period->fresh()->status)->toBe('closed');
    $new = app(DocumentPoster::class)->repost($e, $this->admin->id, 'fix');
    expect($e->fresh()->status)->toBe('reversed')->and($new->status)->toBe('posted')->and($new->entry_date->toDateString())->toBe(now()->toDateString())
        ->and(JournalEntry::find($e->fresh()->reversed_by_id)->entry_date->toDateString())->toBe(now()->toDateString())
        ->and(($this->bal)('receivable'))->toBe((float) $o->total)->and(($this->bal)('cogs'))->toBe(120.0);
    // close the current month too: a repost must fail cleanly, leaving the live entry intact
    ($this->closeThrough)(now()->toDateString());
    $count = JournalEntry::count();
    expect(fn () => app(DocumentPoster::class)->repost($new->fresh(), $this->admin->id, 'again'))->toThrow(AccountingException::class);
    expect(JournalEntry::count())->toBe($count)->and($new->fresh()->status)->toBe('posted')->and(($this->bal)('receivable'))->toBe((float) $o->total);
});

// ---------------------------------------------------------------- QA-012 / 013: duplicate ref draft -> post
it('VERIFY QA-012/013: re-submitting a draft journal form with the same ref (now "post now") never creates a second entry; a changed amount is refused', function () {
    app(CoaTemplate::class)->seed();
    $cash = Account::where('role_key', 'cash')->value('id');
    $cap = Account::where('role_key', 'owner_capital')->value('id');
    $form = fn (string $amt, bool $post) => ['entry_date' => now()->toDateString(), 'description' => 'd', 'type' => 'manual', 'ref' => 'so-ref-1', 'post_now' => $post ? 1 : 0,
        'lines' => [['account_id' => $cash, 'debit' => $amt], ['account_id' => $cap, 'credit' => $amt]]];
    $this->post("{$this->t}/erp/journals", $form('50', false))->assertRedirect()->assertSessionHasNoErrors();
    $this->post("{$this->t}/erp/journals", $form('50', true))->assertRedirect()->assertSessionHasNoErrors();
    expect(JournalEntry::where('idempotency_key', 'manual:so-ref-1')->count())->toBe(1)->and(JournalEntry::count())->toBe(1)
        ->and(($this->bal)('cash'))->toBe(0.0);                                     // the draft was not silently posted by the replay
    $this->post("{$this->t}/erp/journals", $form('70', true))->assertSessionHasErrors('erp');
    $e = JournalEntry::first();
    $this->post("{$this->t}/erp/journals/{$e->id}/post")->assertRedirect();
    expect($e->fresh()->status)->toBe('posted')->and(($this->bal)('cash'))->toBe(50.0);
    $this->post("{$this->t}/erp/journals", $form('50', true))->assertRedirect()->assertSessionHasNoErrors();
    expect(JournalEntry::count())->toBe(1)->and(($this->bal)('cash'))->toBe(50.0);
});

// ---------------------------------------------------------------- QA-004/006: ErpRules boundaries
it('VERIFY QA-004/006: ErpRules money boundaries (max, 0, negative, exponent, Arabic-Indic digits, arrays)', function () {
    $ok = fn (mixed $v, bool $positive = true) => Validator::make(['a' => $v], ['a' => ErpRules::money(true, $positive)])->passes();
    expect($ok('99999999999999.99'))->toBeTrue()
        ->and($ok('100000000000000'))->toBeFalse()
        ->and($ok('99999999999999.999'))->toBeFalse()
        ->and($ok('0.01'))->toBeTrue()
        ->and($ok('0'))->toBeFalse()->and($ok('0', false))->toBeTrue()
        ->and($ok('-1'))->toBeFalse()->and($ok('-1', false))->toBeFalse()
        ->and($ok('1e3'))->toBeFalse()->and($ok('0x1A'))->toBeFalse()->and($ok(' 12'))->toBeFalse()->and($ok('1,000'))->toBeFalse()
        ->and($ok('١٢٣'))->toBeFalse()->and($ok(['1']))->toBeFalse()->and($ok(12))->toBeTrue()->and($ok(12.5))->toBeTrue();
    $pct = fn (mixed $v) => Validator::make(['a' => $v], ['a' => ErpRules::percent()])->passes();
    expect($pct('100'))->toBeTrue()->and($pct('100.0001'))->toBeFalse()->and($pct('0'))->toBeTrue()->and($pct('1e1'))->toBeFalse()->and($pct('12.34567'))->toBeFalse();
    $date = fn (string $v) => Validator::make(['a' => $v], ['a' => ErpRules::date()])->passes();
    expect($date('1999-12-31'))->toBeFalse()->and($date('2000-01-01'))->toBeTrue()->and($date(now()->addYear()->addDay()->toDateString()))->toBeFalse();
    // end-to-end: a max-value balanced journal posts, the balance column holds it, and Arabic digits are a field error (not a 500)
    app(CoaTemplate::class)->seed();
    $cash = Account::where('role_key', 'cash')->value('id');
    $cap = Account::where('role_key', 'owner_capital')->value('id');
    $j = fn ($amt, $ref) => ['entry_date' => now()->toDateString(), 'description' => 'max', 'type' => 'manual', 'ref' => $ref, 'post_now' => 1,
        'lines' => [['account_id' => $cash, 'debit' => $amt], ['account_id' => $cap, 'credit' => $amt]]];
    $this->post("{$this->t}/erp/journals", $j('99999999999999.99', 'mx1'))->assertRedirect()->assertSessionHasNoErrors();
    expect((string) Account::find($cash)->current_balance)->toBe('99999999999999.9900');
    $this->post("{$this->t}/erp/journals", $j('١٢٣', 'mx2'))->assertSessionHasErrors('lines.0.debit');
    $this->post("{$this->t}/erp/journals", $j('-5', 'mx3'))->assertSessionHasErrors('lines.0.debit');
    $this->post("{$this->t}/expenses", ['expense_category_id' => ExpenseCategory::create(['name' => ['ar' => 'ك', 'en' => 'C'], 'is_active' => true])->id,
        'amount' => '١٢٣', 'expense_date' => now()->toDateString()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('amount');
});

// ---------------------------------------------------------------- QA-015 / ERP-016: closing then backdated expense
it('VERIFY QA-015/ERP-016: after closing last month a backdated expense is a field error; future months and the running year cannot be closed', function () {
    $cat = ExpenseCategory::create(['name' => ['ar' => 'ك', 'en' => 'C'], 'is_active' => true]);
    $last = now()->subMonthNoOverflow()->startOfMonth()->addDays(3)->toDateString();
    ($this->closeThrough)($last);
    $this->post("{$this->t}/expenses", ['expense_category_id' => $cat->id, 'amount' => '10', 'expense_date' => $last], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonValidationErrors('expense_date');
    $this->post("{$this->t}/expenses", ['expense_category_id' => $cat->id, 'amount' => '10', 'expense_date' => now()->toDateString()], ['Accept' => 'application/json'])->assertSuccessful();
    $e = Expense::latest('id')->first();
    // moving an open-period expense back into the closed month is refused too
    expect(fn () => app(ExpenseService::class)->update($e, new ExpenseData($cat->id, null, '10', $last, null, null, false)))->toThrow(ValidationException::class);
    expect(Expense::count())->toBe(1)->and(($this->bal)('other_expense'))->toBe(10.0);
    $ps = app(PeriodService::class);
    $next = Period::where('starts_on', '>', now()->toDateString())->orderBy('starts_on')->first() ?? $ps->createYear((int) now()->addYear()->year)->periods()->orderBy('starts_on')->first();
    expect(fn () => $ps->close(Period::where('starts_on', '<=', now()->toDateString())->where('ends_on', '>=', now()->toDateString())->first(), $this->admin->id))->not->toThrow(\Throwable::class);
    expect(fn () => $ps->close($next->refresh(), $this->admin->id))->toThrow(AccountingException::class)
        ->and(fn () => $ps->closeYear(\App\Models\Erp\FiscalYear::where('starts_on', now()->startOfYear()->toDateString())->first(), $this->admin->id))->toThrow(AccountingException::class);
});

it('QA-BUG-101: deleting an expense while today\'s period is closed is refused (otherwise the expense disappears but its journal stays)', function () {
    $cat = ExpenseCategory::create(['name' => ['ar' => 'ك', 'en' => 'C'], 'is_active' => true]);
    $e = app(ExpenseService::class)->create(new ExpenseData($cat->id, null, '25.00', now()->toDateString(), null, null, false));
    expect(($this->bal)('other_expense'))->toBe(25.0);
    ($this->closeThrough)(now()->toDateString());                                  // soft close of the running month
    $this->deleteJson("{$this->t}/expenses/{$e->id}")->assertStatus(422);
    expect(Expense::whereKey($e->id)->exists())->toBeTrue()->and(($this->bal)('other_expense'))->toBe(25.0);
});

// ---------------------------------------------------------------- QA-005: legacy garbage settings
it('VERIFY QA-005: legacy exponent/garbage values already stored in erp_settings never brick the profits page', function () {
    $s = app(ErpSettings::class);
    $s->set('reserve_percent', '1e1');
    $s->set('distribution_min_cash_buffer', 'abc');
    $s->set('tax_rate', '١٤');
    $this->get("{$this->t}/erp/profits")->assertOk();
    $this->put("{$this->t}/erp/settings", ['reserve_percent' => '1e1', 'distribution_min_cash_buffer' => '0', 'tax_rate' => '0'])->assertSessionHasErrors('reserve_percent');
});

// ---------------------------------------------------------------- ERP-027 / 028 / 029 / 030: labels, navigation, audit, dashboard
function soUser(array $perms, string $name): Admin
{
    $role = \Spatie\Permission\Models\Role::create(['name' => $name, 'guard_name' => 'admin']);
    $role->givePermissionTo($perms);
    $u = Admin::query()->create(['name' => 'SO '.$name, 'email' => $name.'@egy.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $u->fresh();
}

it('VERIFY ERP-027: every ERP permission module and action has an en + ar label', function () {
    $missing = [];
    foreach (config('permissions') as $guard => $mods) {
        foreach ((array) $mods as $module => $acts) {
            if (! is_array($acts) || ! str_starts_with((string) $module, 'erp')) {
                continue;
            }
            $mk = 'access.modules.'.str_replace('.', '_', $module);
            foreach (['en', 'ar'] as $l) {
                \Illuminate\Support\Facades\Lang::has($mk, $l, false) || $missing[] = "$l $mk";
                foreach ($acts as $a) {
                    \Illuminate\Support\Facades\Lang::has('access.actions.'.$a, $l, false) || $missing[] = "$l access.actions.$a";
                }
            }
        }
    }
    expect(array_values(array_unique($missing)))->toBe([]);
});

it('VERIFY ERP-028/029/030: reports-only user reaches the hub; owners view-only is read-only; audit page gated; main dashboard shows ERP KPIs', function () {
    app(CoaTemplate::class)->seed();
    $this->actingAs(soUser(['erp.reports.view'], 'so-rep'), 'admin');
    $this->get("{$this->t}/erp")->assertOk();
    $this->get("{$this->t}/erp/audit")->assertForbidden();
    $this->get("{$this->t}/")->assertDontSee('data-erp-kpis', false);
    $this->actingAs(soUser(['erp.owners.view'], 'so-own'), 'admin');
    $this->get("{$this->t}/erp/owners")->assertOk();
    $this->post("{$this->t}/erp/owners", ['name' => 'X', 'ownership_percent' => 10])->assertForbidden();
    $this->actingAs(soUser(['erp.audit.view', 'erp.dashboard.view'], 'so-aud'), 'admin');
    $this->get("{$this->t}/erp/audit")->assertOk();
    $this->get("{$this->t}/")->assertOk()->assertSee('data-erp-kpis', false)->assertSee('data-erp-kpi="net_profit"', false);
});
