<?php

use App\DTOs\Orders\CheckoutData;
use App\DTOs\Orders\ReturnRequestData;
use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\OrderStatus as S;
use App\Enums\PayoutStatus;
use App\Enums\StockMovementType as T;
use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\AffiliateBonus;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateTier;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Services\Affiliates\AffiliateBonusService;
use App\Services\Affiliates\AffiliateService;
use App\Services\BarcodeService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\ReturnService;
use App\Services\Stock\StockLedger;
use App\Support\Money;
use Carbon\Carbon;
use Database\Seeders\FoundationSeeder;
use Spatie\Activitylog\Models\Activity;

/** QA sign-off: edge cases of the affiliate sales bonus (AFFILIATE_BONUS_PLAN.md rules 1–7, docs/CALCULATIONS.md §Affiliate sales bonus). */
beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    codUpfront(false);
    settings(['affiliate_bonus_enabled' => true, 'affiliate_bonus_threshold' => '10000.00', 'affiliate_bonus_amount' => '200.00',
        'affiliate_bonus_started_at' => now()->subDay()->format('Y-m-d H:i:s'), 'affiliate_bonus_paused_periods' => [], 'tax_rate' => 0]);
    $this->branch = Branch::first();
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'م', 'en' => 'P'], 'slug' => 'bonus-edge-p', 'sku' => 'BONUSEDGE', 'barcode' => app(BarcodeService::class)->generateUnique()]);
    listProduct($this->product, ['price' => 1000, 'cost' => 600, 'is_active' => true]);
    app(StockLedger::class)->record($this->branch->id, $this->product->id, 0, T::PURCHASE_IN, 900, '600.00');
    $this->aff = Affiliate::where('email', 'aff@egy.test')->first();
    $this->svc = app(AffiliateBonusService::class);
    $this->affs = app(AffiliateService::class);
    $this->place = fn (int $qty, ?Affiliate $aff = null) => app(OrderPlacementService::class)->place(new CheckoutData(
        name: 'Ali', phone: '01000000100', cityId: City::first()->id, address: 'St', shippingCompanyId: ShippingCompany::first()->id,
        paymentMethodId: PaymentMethod::where('type', 'cod')->first()->id, affiliateId: ($aff ?? $this->aff)->id), [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => $qty]])['order'];
    $this->deliver = function (Order $o) {
        foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
            app(OrderStatusService::class)->change($o, $to);
        }

        return $o->refresh();
    };
    $this->sale = fn (int $qty, ?Affiliate $aff = null) => ($this->deliver)(($this->place)($qty, $aff));
    $this->run = fn () => $this->artisan('affiliates:approve-commissions')->assertSuccessful();
    $this->returnQty = function (Order $o, int $qty) {
        $ret = app(ReturnService::class);

        return $ret->approve($ret->request($o->fresh(), new ReturnRequestData([$o->items->first()->id => $qty], 'x', true, false)));
    };
    $this->admin = fn () => $this->actingAs(Admin::first(), 'admin');
    $this->form = fn (array $bonus = []) => $bonus + ['name' => 'Shop', 'currency_code' => 'EGP', 'currency_symbol' => 'ج.م', 'default_locale' => 'ar', 'locales' => ['ar'],
        'timezone' => 'Africa/Cairo', 'affiliate_bonus_threshold' => 10000, 'affiliate_bonus_amount' => 200]; // keys of $bonus win
    $this->other = fn (string $email = 'b-edge@egy.test') => Affiliate::create(['name' => 'B', 'email' => $email, 'password' => 'password', 'status' => AffiliateStatus::APPROVED, 'approved_at' => now()]);
    $this->actingAs(Customer::where('email', 'customer@egy.test')->first(), 'web');
});

// ---------------------------------------------------------------- rule 3: launch boundary

it('launch boundary: placed exactly at started_at counts, one second before does not (placed_at, then created_at fallback)', function () {
    $start = now()->subHours(2)->startOfSecond();
    settings(['affiliate_bonus_started_at' => $start->format('Y-m-d H:i:s')]);
    $at = ($this->sale)(10);
    $before = ($this->sale)(10);
    $at->forceFill(['placed_at' => $start])->saveQuietly();
    $before->forceFill(['placed_at' => $start->copy()->subSecond()])->saveQuietly();
    $this->travel(15)->days();
    expect($this->svc->eligibleSales($this->aff))->toBe('10000.00');

    // no placed_at: created_at decides, with the same boundary
    $at->forceFill(['placed_at' => null, 'created_at' => $start])->saveQuietly();
    $before->forceFill(['placed_at' => null, 'created_at' => $start->copy()->subSecond()])->saveQuietly();
    expect($this->svc->eligibleSales($this->aff))->toBe('10000.00');
    // placed_at wins over created_at when both are set
    $before->forceFill(['placed_at' => $start->copy()->subSecond(), 'created_at' => $start->copy()->addHour()])->saveQuietly();
    expect($this->svc->eligibleSales($this->aff))->toBe('10000.00')->and($this->svc->awardDue())->toBe(1);
});

// ---------------------------------------------------------------- rule 6: pause periods

it('two pauses through the admin endpoint: [from, to) boundaries, an order maturing during a pause is awarded after re-enable', function () {
    ($this->admin)();
    $a = ($this->sale)(10);                                                    // placed while on
    $this->travel(1)->minutes();
    $this->putJson('/admin/settings/general', ($this->form)())->assertOk();    // off (checkbox not sent)
    $this->travel(15)->days();
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(0);                                  // A matured during the pause: nothing while off
    $this->putJson('/admin/settings/general', ($this->form)(['affiliate_bonus_enabled' => 1]))->assertOk();
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(1);                                  // awarded at the first run after re-enable

    $this->travel(1)->minutes();
    $this->putJson('/admin/settings/general', ($this->form)())->assertOk();
    $this->travel(1)->minutes();
    $this->putJson('/admin/settings/general', ($this->form)(['affiliate_bonus_enabled' => 1]))->assertOk();
    $p = $this->svc->pausedPeriods();
    expect($p)->toHaveCount(2)->and($p[0]['to'])->not->toBeNull()->and($p[1]['to'])->not->toBeNull();

    $at = fn (string $s, int $sec = 0) => Carbon::parse($s)->addSeconds($sec);
    foreach ([[$p[0]['from'], 0], [$p[0]['to'], 0], [$p[1]['from'], 30], [$p[1]['to'], 0], [$p[1]['to'], -1]] as [$when, $shift]) {
        ($this->sale)(2)->forceFill(['placed_at' => $at($when, $shift)])->saveQuietly();
    }
    $this->travel(15)->days();
    // counted: A 10,000 + placed exactly at to#1 + placed exactly at to#2; NOT at from#1, inside pause #2, 1 s before to#2
    expect($this->svc->eligibleSales($this->aff))->toBe('14000.00')->and($this->svc->awardDue())->toBe(0);
    ($this->sale)(6);                                                          // placed after the last re-enable
    $this->travel(15)->days();
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(2)->and((string) AffiliateBonus::where('milestone', 2)->value('sales_at_award'))->toBe('20000.00');
});

it('an order placed inside a pause never counts, also after re-enable and maturity', function () {
    ($this->admin)();
    $this->putJson('/admin/settings/general', ($this->form)())->assertOk();    // off
    $this->travel(1)->minutes();
    ($this->sale)(10);                                                          // placed while off
    $this->travel(1)->minutes();
    $this->putJson('/admin/settings/general', ($this->form)(['affiliate_bonus_enabled' => 1]))->assertOk();
    $this->travel(30)->days();
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(0)->and($this->svc->eligibleSales($this->aff))->toBe('0.00')
        ->and($this->svc->progress($this->aff)['toward_next'])->toBe('0.00');
});

// ---------------------------------------------------------------- rule 2: returns after the award / after the window

it('full return after a bonus: the bonus stays payable, the next bonus needs the lost amount back', function () {
    $o = ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    $b = AffiliateBonus::sole();
    ($this->returnQty)($o, 10);                                                // late full return
    expect($o->fresh()->status)->toBe(S::RETURNED)->and(AffiliateCommission::where('order_id', $o->id)->value('status'))->toBe(CommissionStatus::VOIDED);
    expect($b->fresh()->status)->toBe(CommissionStatus::APPROVED)->and($this->svc->totals($this->aff)['available'])->toBe('200.00')
        ->and($this->svc->eligibleSales($this->aff))->toBe('0.00')->and($this->svc->progress($this->aff)['toward_next'])->toBe('0.00');
    expect(JournalEntry::where('idempotency_key', 'like', 'affbonus:'.$b->id.'%')->count())->toBe(1);  // never reversed

    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(1);                                  // 10,000 eligible − 10,000 consumed
    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(2);
    $p = $this->affs->requestPayout($this->aff);
    expect((string) $p->bonus_amount)->toBe('400.00');
});

it('partial return approved after the window but before the daily run is subtracted', function () {
    $o = ($this->sale)(10);
    $this->travel(15)->days();                                                 // window passed, run not yet
    ($this->returnQty)($o, 1);
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(0)->and($this->svc->eligibleSales($this->aff))->toBe('9000.00');
    $c = AffiliateCommission::where('order_id', $o->id)->first();
    expect($c->status)->toBe(CommissionStatus::APPROVED)->and((string) $c->amount)->toBe('450.00');
});

it('tax-included order with a partial return counts goods excl. VAT', function () {
    settings(['tax_rate' => 14, 'prices_include_tax' => true]);
    $o = ($this->sale)(13);                                                    // 13,000 incl. 14 % VAT
    expect($o->taxIncluded())->toBeTrue()->and((string) $o->tax)->toBe('1596.49')->and($o->revenueGoods())->toBe('11403.51');
    ($this->returnQty)($o, 1);                                                 // 1,000 incl. => 877.19 excl.
    $this->travel(15)->days();
    expect($this->svc->eligibleSales($this->aff))->toBe('10526.32')->and($this->svc->awardDue())->toBe(1);
    expect($this->svc->progress($this->aff)['toward_next'])->toBe('526.32');
});

// ---------------------------------------------------------------- order reassignment (documents current behaviour)

it('order affiliate_id reassigned after a bonus: A keeps the bonus, the order now counts for B (no app path does this; DB edit only)', function () {
    $o = ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    $b = ($this->other)();
    $o->forceFill(['affiliate_id' => $b->id])->saveQuietly();
    ($this->run)();
    expect(AffiliateBonus::where('affiliate_id', $this->aff->id)->count())->toBe(1)       // A keeps it
        ->and($this->svc->eligibleSales($this->aff))->toBe('0.00')
        ->and(AffiliateBonus::where('affiliate_id', $b->id)->count())->toBe(1);           // same 10,000 rewarded twice
    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    expect(AffiliateBonus::where('affiliate_id', $this->aff->id)->count())->toBe(1);      // A needs 20,000 for #2
});

// ---------------------------------------------------------------- payouts: stored split

it('payout split: rejected keeps it, paid recomputes it after a commission was voided; API, dashboard and admin datatable show the stored split', function () {
    $o1 = ($this->sale)(6);
    $o2 = ($this->sale)(5);                                                    // 11,000 => 1 bonus; two commissions (tier from lifetime sales)
    $this->travel(15)->days();
    ($this->run)();
    $c1 = (string) AffiliateCommission::where('order_id', $o1->id)->value('amount');
    $c2 = (string) AffiliateCommission::where('order_id', $o2->id)->value('amount');
    $both = \App\Support\Money::add($c1, $c2);
    expect(AffiliateBonus::count())->toBe(1)->and(Money::cmp($c1, '0'))->toBe(1)->and(Money::cmp($c2, '0'))->toBe(1);
    $p1 = $this->affs->requestPayout($this->aff);
    expect((string) $p1->commission_amount)->toBe($both)->and((string) $p1->bonus_amount)->toBe('200.00')->and((string) $p1->amount)->toBe(Money::add($both, '200'));
    $this->affs->rejectPayout($p1);

    $p2 = $this->affs->requestPayout($this->aff);
    ($this->returnQty)($o2, 5);                                                // full return: commission voided and detached from the payout
    expect(AffiliateCommission::where('order_id', $o2->id)->first())->status->toBe(CommissionStatus::VOIDED)->payout_id->toBeNull();
    expect((string) $p2->fresh()->amount)->toBe(Money::add($both, '200'));     // requested: stored at request, recomputed only when paid
    $p2 = $this->affs->markPaid($p2->fresh());
    expect((string) $p2->commission_amount)->toBe($c1)->and((string) $p2->bonus_amount)->toBe('200.00')->and((string) $p2->amount)->toBe(Money::add($c1, '200'));
    $p1 = $p1->fresh();
    expect($p1->status)->toBe(PayoutStatus::REJECTED)->and((string) $p1->commission_amount)->toBe($both)->and((string) $p1->bonus_amount)->toBe('200.00');
    expect(AffiliateBonus::sole()->status)->toBe(CommissionStatus::PAID);

    $token = $this->aff->createToken('t', ['affiliate'])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/affiliate/payouts')->assertOk()
        ->assertJsonPath('data.0.id', $p2->id)->assertJsonPath('data.0.commission_amount', $c1)->assertJsonPath('data.0.bonus_amount', '200.00')->assertJsonPath('data.0.amount', Money::add($c1, '200'))
        ->assertJsonPath('data.1.status', 'rejected')->assertJsonPath('data.1.commission_amount', $both)->assertJsonPath('data.1.bonus_amount', '200.00');
    $this->withToken($token)->getJson('/api/v1/affiliate/dashboard')->assertOk()
        ->assertJsonPath('data.payouts.0.commission_amount', $c1)->assertJsonPath('data.payouts.1.commission_amount', $both)
        ->assertJsonPath('data.recent_bonuses.0.status', 'paid')->assertJsonPath('data.recent_bonuses.0.payout_id', $p2->id);

    app('auth')->forgetGuards();
    ($this->admin)();
    $rows = collect($this->getJson('/admin/affiliate-payouts/datatable?draw=1&start=0&length=10')->assertOk()->json('data'))->keyBy('id');
    $fmt = fn (string $v) => number_format((float) $v, 2);
    expect($rows[$p2->id]['commission_amount'])->toBe($fmt($c1))->and($rows[$p2->id]['bonus_amount'])->toBe('200.00')->and($rows[$p2->id]['amount'])->toBe($fmt(Money::add($c1, '200')))
        ->and($rows[$p1->id]['commission_amount'])->toBe($fmt($both))->and($rows[$p1->id]['bonus_amount'])->toBe('200.00');
});

it('bonuses-only payout stores commission 0.00 / bonus 200.00 and pays through the admin endpoint', function () {
    AffiliateTier::query()->delete();
    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    $p = $this->affs->requestPayout($this->aff);
    expect((string) $p->commission_amount)->toBe('0.00')->and((string) $p->bonus_amount)->toBe('200.00');
    ($this->admin)();
    $this->postJson("/admin/affiliate-payouts/{$p->id}/pay")->assertOk();
    $p = $p->fresh();
    expect($p->status)->toBe(PayoutStatus::PAID)->and((string) $p->amount)->toBe('200.00')->and((string) $p->commission_amount)->toBe('0.00')->and((string) $p->bonus_amount)->toBe('200.00');
    $row = collect($this->getJson('/admin/affiliate-payouts/datatable?draw=1&start=0&length=10')->json('data'))->firstWhere('id', $p->id);
    expect($row['commission_amount'])->toBe('0.00')->and($row['bonus_amount'])->toBe('200.00');
});

// ---------------------------------------------------------------- settings via the real endpoint

it('rejects amount 0, amount ≥ threshold and threshold < 1000 with 422 on the admin update endpoint, saving nothing', function () {
    ($this->admin)();
    foreach ([[10000, 0, 'affiliate_bonus_amount'], [5000, 5000, 'affiliate_bonus_amount'], [5000, 6000, 'affiliate_bonus_amount'], [999, 50, 'affiliate_bonus_threshold'], [10000, '', 'affiliate_bonus_amount']] as [$t, $a, $field]) {
        $this->putJson('/admin/settings/general', ($this->form)(['affiliate_bonus_enabled' => 1, 'affiliate_bonus_threshold' => $t, 'affiliate_bonus_amount' => $a]))
            ->assertStatus(422)->assertJsonValidationErrors([$field]);
    }
    expect($this->svc->threshold())->toBe('10000.00')->and($this->svc->amount())->toBe('200.00');
    $this->putJson('/admin/settings/general', ($this->form)(['affiliate_bonus_enabled' => 1, 'affiliate_bonus_threshold' => 1000, 'affiliate_bonus_amount' => '999.99']))->assertOk();
    expect($this->svc->threshold())->toBe('1000.00')->and($this->svc->amount())->toBe('999.99');
});

it('writes a settings audit log entry for bonus changes (and none when nothing changed)', function () {
    ($this->admin)();
    $this->putJson('/admin/settings/general', ($this->form)(['affiliate_bonus_enabled' => 1, 'affiliate_bonus_threshold' => 8000, 'affiliate_bonus_amount' => 150]))->assertOk();
    $log = Activity::where('log_name', 'settings')->latest('id')->first();
    expect($log)->not->toBeNull()->and($log->causer_id)->toBe(Admin::first()->id)
        ->and($log->properties['affiliate_bonus_threshold'])->toBe('8000.00')->and($log->properties['affiliate_bonus_amount'])->toBe('150.00')
        ->and($log->properties->has('affiliate_bonus_enabled'))->toBeFalse();
    $n = Activity::where('log_name', 'settings')->count();
    $this->putJson('/admin/settings/general', ($this->form)(['affiliate_bonus_enabled' => 1, 'affiliate_bonus_threshold' => 8000, 'affiliate_bonus_amount' => 150]))->assertOk();
    expect(Activity::where('log_name', 'settings')->count())->toBe($n);
    $this->putJson('/admin/settings/general', ($this->form)(['affiliate_bonus_threshold' => 8000, 'affiliate_bonus_amount' => 150]))->assertOk(); // off
    expect(Activity::where('log_name', 'settings')->latest('id')->first()->properties['affiliate_bonus_enabled'])->toBeFalse();
});

it('threshold changed mid-way through the admin endpoint keeps old bonuses and uses the new value next', function () {
    ($this->admin)();
    ($this->sale)(15);
    $this->travel(15)->days();
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(1);
    $this->putJson('/admin/settings/general', ($this->form)(['affiliate_bonus_enabled' => 1, 'affiliate_bonus_threshold' => 4000, 'affiliate_bonus_amount' => 100]))->assertOk();
    ($this->run)();                                                            // 15,000 − 10,000 = 5,000 ≥ 4,000
    $rows = AffiliateBonus::orderBy('milestone')->get();
    expect($rows)->toHaveCount(2)->and((string) $rows[0]->threshold)->toBe('10000.00')->and((string) $rows[0]->amount)->toBe('200.00')
        ->and((string) $rows[1]->threshold)->toBe('4000.00')->and((string) $rows[1]->amount)->toBe('100.00');
    expect($this->svc->progress($this->aff))->toMatchArray(['toward_next' => '1000.00', 'remaining' => '3000.00', 'consumed' => '14000.00']);
    $this->putJson('/admin/settings/general', ($this->form)(['affiliate_bonus_enabled' => 1, 'affiliate_bonus_threshold' => 20000, 'affiliate_bonus_amount' => 300]))->assertOk();
    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();                                                            // 25,000 − 14,000 = 11,000 < 20,000
    expect(AffiliateBonus::count())->toBe(2)->and($this->svc->progress($this->aff)['toward_next'])->toBe('11000.00');
});

// ---------------------------------------------------------------- command / ERP idempotency

it('running the command twice awards once and reports it; ERP entry posted once; with auto-posting off the backfill posts it once', function () {
    ($this->sale)(10);
    $this->travel(15)->days();
    $erp = app(\App\Services\Erp\Equity\ErpSettings::class);
    $erp->set('auto_posting_enabled', false);
    $this->artisan('affiliates:approve-commissions')->expectsOutputToContain('1 bonuses awarded')->assertSuccessful();
    $this->artisan('affiliates:approve-commissions')->expectsOutputToContain('0 bonuses awarded')->assertSuccessful();
    $b = AffiliateBonus::sole();
    expect(JournalEntry::where('idempotency_key', 'like', 'affbonus:%')->count())->toBe(0);
    $erp->set('auto_posting_enabled', true);
    $bf = app(\App\Services\Erp\Backfill\BackfillService::class);
    $bf->missing(true);
    ($this->run)();
    $bf->missing(true);
    $e = JournalEntry::where('idempotency_key', 'like', 'affbonus:%')->get();
    expect($e)->toHaveCount(1)->and($e[0]->idempotency_key)->toStartWith('affbonus:'.$b->id)->and($e[0]->source_type)->toBe('affiliate_bonus');
    expect((float) Account::where('role_key', 'affiliate_payable')->value('current_balance'))->toBeGreaterThanOrEqual(200.0);
});

// ---------------------------------------------------------------- affiliate deleted with unpaid bonuses (documents behaviour)

it('refuses to delete an affiliate who is still owed money, and allows it once everything is paid', function () {
    AffiliateTier::query()->delete();                                          // bonus only, so the payable is exactly the bonus
    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    $b = AffiliateBonus::sole();
    $payable = fn () => (float) Account::where('role_key', 'affiliate_payable')->value('current_balance');
    expect(JournalEntry::where('idempotency_key', 'affbonus:'.$b->id)->exists())->toBeTrue()->and($payable())->toBe(200.0);
    ($this->admin)();

    // approved bonus, then a requested payout: both refused, nothing removed
    $this->deleteJson("/admin/affiliates/{$this->aff->id}")->assertStatus(422)->assertJsonValidationErrors(['affiliate']);
    $p = $this->affs->requestPayout($this->aff);
    $this->deleteJson("/admin/affiliates/{$this->aff->id}")->assertStatus(422)->assertJsonPath('errors.affiliate.0', __('growth.affiliate_has_unpaid'));
    expect(Affiliate::find($this->aff->id))->not->toBeNull()->and(AffiliateBonus::count())->toBe(1)->and(AffiliatePayout::count())->toBe(1);

    // paid: the payable is cleared, so deleting leaves no orphan liability
    $this->affs->markPaid($p->fresh());
    expect($payable())->toBe(0.0);
    $this->deleteJson("/admin/affiliates/{$this->aff->id}")->assertOk();
    expect(Affiliate::find($this->aff->id))->toBeNull()->and($payable())->toBe(0.0);
});

it('refuses to delete an affiliate with a pending commission', function () {
    ($this->sale)(1);                                                          // placed, not yet past the return window
    expect(App\Models\AffiliateCommission::where('affiliate_id', $this->aff->id)->where('status', 'pending')->exists())->toBeTrue();
    ($this->admin)();
    $this->deleteJson("/admin/affiliates/{$this->aff->id}")->assertStatus(422);
    expect(Affiliate::find($this->aff->id))->not->toBeNull();
});

// ---------------------------------------------------------------- authorization

it('an affiliate only sees their own bonuses (web + API); guests and customers are refused', function () {
    $b = ($this->other)();
    ($this->sale)(25, $b);                                                     // B: 2 bonuses, 5,000 toward the next
    ($this->sale)(3);                                                          // A: 3,000, no bonus
    $this->travel(15)->days();
    ($this->run)();
    expect(AffiliateBonus::where('affiliate_id', $b->id)->count())->toBe(2)->and(AffiliateBonus::where('affiliate_id', $this->aff->id)->count())->toBe(0);
    $this->affs->requestPayout($b);

    // guests / customer session -> affiliate login
    $this->get('/en/affiliate')->assertRedirect('/en/affiliate/login');        // logged in as a customer (web guard) only
    auth('web')->logout();
    $this->get('/en/affiliate')->assertRedirect('/en/affiliate/login');
    $this->getJson('/api/v1/affiliate/dashboard')->assertUnauthorized();
    $this->getJson('/api/v1/affiliate/payouts')->assertUnauthorized();
    $customerToken = Customer::where('email', 'customer@egy.test')->first()->createToken('c', ['customer'])->plainTextToken;
    expect($this->withToken($customerToken)->getJson('/api/v1/affiliate/dashboard')->status())->toBeIn([401, 403]);
    app('auth')->forgetGuards();

    $token = $this->aff->createToken('t', ['affiliate'])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/affiliate/dashboard')->assertOk()
        ->assertJsonPath('data.stats.bonus.earned_count', 0)->assertJsonPath('data.stats.bonus.toward_next', '3000.00')
        ->assertJsonCount(0, 'data.recent_bonuses')->assertJsonCount(0, 'data.payouts');
    $this->withToken($token)->getJson('/api/v1/affiliate/payouts')->assertOk()->assertJsonCount(0, 'data');
    app('auth')->forgetGuards();
    $this->withHeaders(['Authorization' => ''])->actingAs($this->aff, 'affiliate');
    app()->setLocale('en');
    $html = $this->get('/en/affiliate')->assertOk()->getContent();
    expect($html)->toContain(e(__('growth.bonus_progress', ['done' => '3,000.00', 'threshold' => '10,000.00 EGP'])))
        ->not->toContain(e(__('growth.bonus_progress', ['done' => '5,000.00', 'threshold' => '10,000.00 EGP'])))
        ->not->toContain('#2');
});

it('the bonus settings need the General settings permissions (view to see, edit to change)', function () {
    $mk = function (array $perms, string $name) {
        $role = \Spatie\Permission\Models\Role::create(['name' => $name, 'guard_name' => 'admin']);
        $role->givePermissionTo($perms);
        $u = Admin::query()->create(['name' => $name, 'email' => $name.'@egy.test', 'password' => 'password', 'is_active' => true]);
        $u->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    };
    $put = ($this->form)(['affiliate_bonus_enabled' => 1, 'affiliate_bonus_threshold' => 2000, 'affiliate_bonus_amount' => 1500]);

    $this->actingAs($mk(['affiliates.view', 'affiliates.edit', 'affiliate_payouts.view', 'affiliate_payouts.edit'], 'affmanager'), 'admin');
    $this->get('/admin/settings/general')->assertForbidden();
    $this->putJson('/admin/settings/general', $put)->assertForbidden();
    $this->postJson('/admin/settings/general/validate', $put)->assertForbidden();

    $this->actingAs($mk(['general_settings.view'], 'gsviewer'), 'admin');
    $this->get('/admin/settings/general')->assertOk()->assertSee('name="affiliate_bonus_threshold"', false);
    $this->putJson('/admin/settings/general', $put)->assertForbidden();
    expect($this->svc->threshold())->toBe('10000.00')->and($this->svc->amount())->toBe('200.00');

    $this->actingAs($mk(['general_settings.view', 'general_settings.edit'], 'gseditor'), 'admin');
    $this->putJson('/admin/settings/general', $put)->assertOk();
    expect($this->svc->threshold())->toBe('2000.00');
});
