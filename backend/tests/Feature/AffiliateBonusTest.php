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
use App\Models\AffiliateTier;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\Erp\JournalEntry;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Services\Affiliates\AffiliateBonusService;
use App\Services\Affiliates\AffiliateCommissionService;
use App\Services\Affiliates\AffiliateService;
use App\Services\BarcodeService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\ReturnService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Validation\ValidationException;

/** Affiliate sales bonus: 200 EGP for every 10,000 EGP of eligible sales (docs/CALCULATIONS.md §Affiliate sales bonus). */
beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    codUpfront(false);
    settings(['affiliate_bonus_enabled' => true, 'affiliate_bonus_threshold' => '10000.00', 'affiliate_bonus_amount' => '200.00',
        'affiliate_bonus_started_at' => now()->subDay()->format('Y-m-d H:i:s'), 'tax_rate' => 0]);
    $this->branch = Branch::first();
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'م', 'en' => 'P'], 'slug' => 'bonus-p', 'sku' => 'BONUSP', 'barcode' => app(BarcodeService::class)->generateUnique()]);
    listProduct($this->product, ['price' => 1000, 'cost' => 600, 'is_active' => true]);
    app(StockLedger::class)->record($this->branch->id, $this->product->id, 0, T::PURCHASE_IN, 500, '600.00');
    $this->aff = Affiliate::where('email', 'aff@egy.test')->first();
    $this->svc = app(AffiliateBonusService::class);
    // order of $qty × 1,000 attributed to the affiliate
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
    $this->actingAs(Customer::where('email', 'customer@egy.test')->first(), 'web');
});

it('awards exactly one bonus at 10,000 once the return window passed, and is idempotent', function () {
    $o = ($this->sale)(10);
    expect($o->revenueGoods())->toBe('10000.00');
    expect($this->svc->awardDue())->toBe(0);                                   // inside the return window
    expect($this->svc->progress($this->aff)['eligible_sales'])->toBe('0.00');

    $this->travel(15)->days();
    ($this->run)();
    ($this->run)();                                                            // double run
    expect($this->svc->awardDue())->toBe(0);
    $b = AffiliateBonus::sole();
    expect($b->milestone)->toBe(1)->and((string) $b->threshold)->toBe('10000.00')->and((string) $b->amount)->toBe('200.00')
        ->and((string) $b->sales_at_award)->toBe('10000.00')->and($b->status)->toBe(CommissionStatus::APPROVED);
    $p = $this->svc->progress($this->aff);
    expect($p['earned_count'])->toBe(1)->and($p['earned_total'])->toBe('200.00')->and($p['toward_next'])->toBe('0.00')->and($p['remaining'])->toBe('10000.00');
});

it('awards two bonuses at 20,000+ and reports progress toward the next one', function () {
    ($this->sale)(12);
    ($this->sale)(9);                                                          // 21,000
    $this->travel(15)->days();
    expect($this->svc->awardDue())->toBe(2);
    expect(AffiliateBonus::orderBy('milestone')->pluck('milestone')->all())->toBe([1, 2]);
    $p = $this->svc->progress($this->aff);
    expect($p['eligible_sales'])->toBe('21000.00')->and($p['consumed'])->toBe('20000.00')->and($p['toward_next'])->toBe('1000.00')
        ->and($p['percent'])->toBe(10.0)->and($p['available'])->toBe('400.00');
    expect(app(AffiliateCommissionService::class)->totals($this->aff)['available_bonuses'])->toBe('400.00');
});

it('ignores orders placed before the launch date, and cancelled orders', function () {
    $old = ($this->sale)(10);
    $old->forceFill(['created_at' => now()->subDays(3), 'placed_at' => now()->subDays(3)])->saveQuietly(); // before started_at (now − 1 day)
    $c = ($this->place)(10);
    app(OrderStatusService::class)->change($c, S::CANCELLED);
    $this->travel(15)->days();
    expect($this->svc->awardDue())->toBe(0)->and($this->svc->eligibleSales($this->aff))->toBe('0.00');
});

it('counts orders of a 0 % tier (no commission row)', function () {
    AffiliateTier::query()->delete();
    $o = ($this->sale)(10);
    expect((string) $o->affiliate_commission)->toBe('0.00')->and(AffiliateCommission::where('order_id', $o->id)->exists())->toBeFalse();
    $this->travel(15)->days();
    expect($this->svc->awardDue())->toBe(1);
});

it('subtracts returned goods; a later return only delays the next bonus', function () {
    $o = ($this->sale)(10);
    $ret = app(ReturnService::class);
    $ret->approve($ret->request($o, new ReturnRequestData([$o->items->first()->id => 1], 'x', true, false)));
    $this->travel(15)->days();
    expect($this->svc->eligibleSales($this->aff))->toBe('9000.00')->and($this->svc->awardDue())->toBe(0);

    ($this->sale)(2);                                                          // 11,000 placed, 9,000 + 2,000 once past the window
    $this->travel(15)->days();
    expect($this->svc->awardDue())->toBe(1);
    $o2 = ($this->sale)(10);
    $ret->approve($ret->request($o2, new ReturnRequestData([$o2->items->first()->id => 5], 'x', true, false)));
    $this->travel(15)->days();
    expect($this->svc->awardDue())->toBe(0)->and(AffiliateBonus::count())->toBe(1); // 16,000 − 10,000 consumed = 6,000
});

it('gives a suspended affiliate nothing, then catches up after re-approval', function () {
    ($this->sale)(20);
    app(AffiliateService::class)->setStatus($this->aff, AffiliateStatus::SUSPENDED);
    $this->travel(15)->days();
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(0);
    app(AffiliateService::class)->setStatus($this->aff->fresh(), AffiliateStatus::APPROVED);
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(2);
});

it('keeps earned bonuses when the settings change and uses the new ones for the next bonus; disabled awards nothing', function () {
    ($this->sale)(10);
    $this->travel(15)->days();
    expect($this->svc->awardDue())->toBe(1);
    settings(['affiliate_bonus_threshold' => '5000', 'affiliate_bonus_amount' => '150']);
    ($this->sale)(5);
    $this->travel(15)->days();
    settings(['affiliate_bonus_enabled' => false]);
    expect($this->svc->awardDue())->toBe(0);
    settings(['affiliate_bonus_enabled' => true]);
    expect($this->svc->awardDue())->toBe(1);
    $rows = AffiliateBonus::orderBy('milestone')->get();
    expect((string) $rows[0]->threshold)->toBe('10000.00')->and((string) $rows[0]->amount)->toBe('200.00')
        ->and((string) $rows[1]->threshold)->toBe('5000.00')->and((string) $rows[1]->amount)->toBe('150.00');
});

it('pays bonuses through payouts: request (bonus only), reject frees, paid marks them', function () {
    AffiliateTier::query()->delete();                                          // no commission: a bonus-only payout
    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    $affs = app(AffiliateService::class);
    expect(app(AffiliateCommissionService::class)->totals($this->aff)['available'])->toBe('200.00');

    $p = $affs->requestPayout($this->aff);
    expect((string) $p->amount)->toBe('200.00')->and(AffiliateBonus::sole()->payout_id)->toBe($p->id);
    expect(fn () => $affs->requestPayout($this->aff))->toThrow(ValidationException::class);
    $affs->rejectPayout($p);
    expect(AffiliateBonus::sole()->payout_id)->toBeNull();

    $p2 = $affs->requestPayout($this->aff);
    $affs->markPaid($p2);
    expect(AffiliateBonus::sole()->status)->toBe(CommissionStatus::PAID)->and((string) $p2->fresh()->amount)->toBe('200.00')
        ->and($p2->fresh()->status)->toBe(PayoutStatus::PAID);
    expect($this->svc->totals($this->aff)['paid'])->toBe('200.00')->and($this->svc->totals($this->aff)['available'])->toBe('0.00');
});

it('payout amount = paid commissions + paid bonuses', function () {
    ($this->sale)(10);                                                         // tier 5 % -> 500 commission
    $this->travel(15)->days();
    ($this->run)();
    $affs = app(AffiliateService::class);
    $commission = (string) AffiliateCommission::sole()->amount;
    $p = $affs->requestPayout($this->aff);
    expect((string) $p->amount)->toBe(\App\Support\Money::add($commission, '200'));
    $affs->markPaid($p);
    expect((string) $p->fresh()->amount)->toBe(\App\Support\Money::add($commission, '200'));
});

it('posts the bonus to the ERP once (Dr affiliate expense / Cr affiliate payable)', function () {
    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    ($this->run)();
    $b = AffiliateBonus::sole();
    $e = JournalEntry::where('idempotency_key', 'affbonus:'.$b->id)->get();
    expect($e)->toHaveCount(1)->and($e[0]->source_type)->toBe('affiliate_bonus');
    $lines = $e[0]->lines()->with('account')->get();
    expect($lines->firstWhere(fn ($l) => $l->account->role_key === 'affiliate_expense')->debit)->toEqual('200.0000')
        ->and($lines->firstWhere(fn ($l) => $l->account->role_key === 'affiliate_payable')->credit)->toEqual('200.0000');
});

it('exposes the bonus block in the mobile API dashboard and payouts', function () {
    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    app(AffiliateService::class)->requestPayout($this->aff);
    $token = $this->aff->createToken('t', ['affiliate'])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/affiliate/dashboard')->assertOk()
        ->assertJsonPath('data.stats.bonus.earned_count', 1)->assertJsonPath('data.stats.bonus.threshold', '10000.00')
        ->assertJsonPath('data.recent_bonuses.0.amount', '200.00')->assertJsonPath('data.payouts.0.bonus_amount', '200.00');
    $this->withToken($token)->getJson('/api/v1/affiliate/payouts')->assertOk()->assertJsonPath('data.0.bonus_amount', '200.00');
});

it('validates and saves the bonus settings from Settings → General', function () {
    $this->actingAs(Admin::first(), 'admin');
    $base = ['name' => 'Shop', 'currency_code' => 'EGP', 'currency_symbol' => 'ج.م', 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Africa/Cairo'];
    $this->postJson('/admin/settings/general/validate', $base + ['affiliate_bonus_threshold' => 0, 'affiliate_bonus_amount' => -1])
        ->assertStatus(422)->assertJsonValidationErrors(['affiliate_bonus_threshold', 'affiliate_bonus_amount']);
    $started = $this->svc->settings()['affiliate_bonus_started_at'];
    $this->putJson('/admin/settings/general', $base + ['affiliate_bonus_enabled' => 1, 'affiliate_bonus_threshold' => 8000, 'affiliate_bonus_amount' => 100,
        'affiliate_bonus_started_at' => '2020-01-01 00:00:00'])->assertOk();
    expect($this->svc->threshold())->toBe('8000.00')->and($this->svc->amount())->toBe('100.00')->and($this->svc->enabled())->toBeTrue()
        ->and($this->svc->settings()['affiliate_bonus_started_at'])->toBe($started);     // read-only
    $this->putJson('/admin/settings/general', $base)->assertOk();             // section not sent: untouched
    expect($this->svc->threshold())->toBe('8000.00')->and($this->svc->enabled())->toBeTrue();
});

it('shows the bonus card with progress and earned bonuses on the affiliate dashboard (ar + en), hidden when off and none earned', function () {
    ($this->sale)(12);
    $this->travel(15)->days();
    ($this->run)();
    auth('web')->logout();
    $this->actingAs($this->aff, 'affiliate');
    foreach (['ar', 'en'] as $loc) {
        app()->setLocale($loc);
        $html = $this->get("/{$loc}/affiliate")->assertOk()->assertSee('noindex,nofollow', false)->getContent();
        expect($html)->toContain('data-bonus-card')->toContain('role="progressbar"')->toContain('aria-valuenow="20"')
            ->toContain(e(__('growth.bonus_rule', ['amount' => '200 EGP', 'threshold' => '10,000 EGP'])))
            ->toContain(e(__('growth.bonus_progress', ['done' => '2,000.00', 'threshold' => '10,000.00 EGP'])))
            ->toContain(e(__('growth.bonus_remaining', ['remaining' => '8,000.00 EGP'])))
            ->toContain('#1')->toContain(e(CommissionStatus::APPROVED->label()))->not->toContain('&lt;');
    }
    settings(['affiliate_bonus_enabled' => false]);
    $this->get('/en/affiliate')->assertOk()->assertSee('data-bonus-card', false)->assertSee(__('growth.bonus_paused'))->assertDontSee('role="progressbar"', false);
    AffiliateBonus::query()->delete();
    $this->get('/en/affiliate')->assertOk()->assertDontSee('data-bonus-card', false);
});

it('shows the commission / bonus split in the dashboard payout history', function () {
    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    app(AffiliateService::class)->requestPayout($this->aff);
    auth('web')->logout();
    $this->actingAs($this->aff, 'affiliate');
    app()->setLocale('en');
    $html = $this->get('/en/affiliate')->assertOk()->getContent();
    $total = \App\Support\Money::add('200.00', (string) AffiliateCommission::sum('amount'));
    expect($html)->toContain('<td class="whitespace-nowrap pe-2">200.00</td>')->toContain(number_format((float) $total, 2));
});

it('advertises the bonus on the public register page from the live settings, with translated title and meta (ar + en)', function () {
    auth('web')->logout();
    settings(['affiliate_bonus_threshold' => '8000.00', 'affiliate_bonus_amount' => '150.00']);
    foreach (['ar', 'en'] as $loc) {
        app()->setLocale($loc);
        $html = $this->get("/{$loc}/affiliate/register")->assertOk()->getContent();
        $line = __('growth.register_bonus', ['amount' => '150 EGP', 'threshold' => '8,000 EGP']);
        expect($html)->toContain('data-bonus-benefit')->toContain(e($line))
            ->toContain('<title>'.e(__('growth.register_title')))
            ->toContain('name="description" content="'.e(\Illuminate\Support\Str::limit(__('growth.register_meta_bonus', ['store' => setting('name'), 'amount' => '150 EGP', 'threshold' => '8,000 EGP']), 158, '')).'"')
            ->toContain('index,follow')->not->toContain('noindex')->and(substr_count($html, '<h1'))->toBe(1);
    }
    settings(['affiliate_bonus_enabled' => false]);
    app()->setLocale('en');
    $this->get('/en/affiliate/register')->assertOk()->assertDontSee('data-bonus-benefit', false)
        ->assertSee('content="'.e(__('growth.register_meta', ['store' => setting('name')])).'"', false);
    $this->get('/robots.txt')->assertSee('Allow: /*/affiliate/register', false);
});

it('renders the bonus section in Settings → General (ar + en) with a read-only launch date', function () {
    $this->actingAs(Admin::first(), 'admin');
    foreach (['ar', 'en'] as $loc) {
        app()->setLocale($loc);
        $html = $this->withSession(['admin_locale' => $loc])->get('/admin/settings/general')->assertOk()->getContent();
        expect($html)->toContain(e(__('admin.affiliate_bonus_section')))->toContain('name="affiliate_bonus_enabled"')
            ->toContain('name="affiliate_bonus_threshold"')->toContain('value="10000.00"')->toContain('name="affiliate_bonus_amount"')->toContain('value="200.00"')
            ->toContain('id="f-affiliate_bonus_started_at"')->not->toContain('name="affiliate_bonus_started_at"')
            ->toContain(e(__('admin.affiliate_bonus_started_hint')));
    }
});

// ---------------------------------------------------------------- review fixes (round 2)

it('never burns sales into 0.00 bonuses: amount 0 awards nothing, and the settings reject amount 0, amount ≥ threshold and threshold < 1000', function () {
    ($this->sale)(10);
    $this->travel(15)->days();
    settings(['affiliate_bonus_amount' => '0']);
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(0);
    settings(['affiliate_bonus_amount' => '200']);
    expect($this->svc->awardDue())->toBe(1);                                  // the sales were kept for the real bonus

    $this->actingAs(Admin::first(), 'admin');
    $base = ['name' => 'Shop', 'currency_code' => 'EGP', 'currency_symbol' => 'ج.م', 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Africa/Cairo'];
    $this->postJson('/admin/settings/general/validate', $base + ['affiliate_bonus_threshold' => 10000, 'affiliate_bonus_amount' => 0])
        ->assertStatus(422)->assertJsonValidationErrors(['affiliate_bonus_amount']);
    $this->postJson('/admin/settings/general/validate', $base + ['affiliate_bonus_threshold' => 5000, 'affiliate_bonus_amount' => 5000])
        ->assertStatus(422)->assertJsonValidationErrors(['affiliate_bonus_amount']);
    $this->postJson('/admin/settings/general/validate', $base + ['affiliate_bonus_threshold' => 100, 'affiliate_bonus_amount' => 50])
        ->assertStatus(422)->assertJsonValidationErrors(['affiliate_bonus_threshold']);
    $this->postJson('/admin/settings/general/validate', $base + ['affiliate_bonus_threshold' => 1000, 'affiliate_bonus_amount' => 20])->assertOk();
});

it('pauses while off: orders placed while off never count, orders placed while on are awarded after re-enable', function () {
    $this->actingAs(Admin::first(), 'admin');
    $base = ['name' => 'Shop', 'currency_code' => 'EGP', 'currency_symbol' => 'ج.م', 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Africa/Cairo',
        'affiliate_bonus_threshold' => 10000, 'affiliate_bonus_amount' => 200];
    ($this->sale)(10);                                                         // placed while on
    $this->travel(1)->minutes();
    $this->putJson('/admin/settings/general', $base)->assertOk();             // checkbox not sent => off
    expect($this->svc->enabled())->toBeFalse()->and($this->svc->pausedPeriods())->toHaveCount(1)->and($this->svc->pausedPeriods()[0]['to'])->toBeNull();
    $this->travel(1)->minutes();
    ($this->sale)(10);                                                         // placed while off: never counts
    $this->travel(15)->days();
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(0);                                 // nothing while off, even though the first order matured
    $this->putJson('/admin/settings/general', $base + ['affiliate_bonus_enabled' => 1])->assertOk();
    expect($this->svc->pausedPeriods()[0]['to'])->not->toBeNull();
    expect($this->svc->eligibleSales($this->aff))->toBe('10000.00');
    ($this->run)();
    expect(AffiliateBonus::count())->toBe(1);                                 // the order placed while on, matured during the pause
    $this->travel(1)->minutes();
    ($this->sale)(5);                                                          // placed after re-enable: counts again
    $this->travel(15)->days();
    expect($this->svc->eligibleSales($this->aff))->toBe('15000.00')->and($this->svc->awardDue())->toBe(0);
});

it('uses placed_at (not created_at) for the launch date', function () {
    $o = ($this->sale)(10);
    $o->forceFill(['placed_at' => now()->subDays(3)])->saveQuietly();         // placed before the launch, row created later
    $this->travel(15)->days();
    expect($this->svc->awardDue())->toBe(0);
    $o->forceFill(['placed_at' => null])->saveQuietly();                       // no placed_at: falls back to created_at (after launch)
    expect($this->svc->awardDue())->toBe(1);
});

it('counts voided bonuses as consumed: a void forfeits those sales instead of re-awarding them', function () {
    ($this->sale)(10);
    $this->travel(15)->days();
    expect($this->svc->awardDue())->toBe(1);
    AffiliateBonus::query()->update(['status' => CommissionStatus::VOIDED->value]);
    ($this->sale)(5);
    $this->travel(15)->days();
    expect($this->svc->awardDue())->toBe(0)->and($this->svc->progress($this->aff)['toward_next'])->toBe('5000.00');
});

it('keeps the commission / bonus split on the payout, also after a rejection', function () {
    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    $affs = app(AffiliateService::class);
    $commission = (string) AffiliateCommission::sole()->amount;
    $p = $affs->requestPayout($this->aff);
    expect((string) $p->commission_amount)->toBe($commission)->and((string) $p->bonus_amount)->toBe('200.00');
    $affs->rejectPayout($p);
    $p = $p->fresh();
    expect($p->status)->toBe(PayoutStatus::REJECTED)->and((string) $p->commission_amount)->toBe($commission)->and((string) $p->bonus_amount)->toBe('200.00');
    $token = $this->aff->createToken('t', ['affiliate'])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/affiliate/payouts')->assertOk()
        ->assertJsonPath('data.0.bonus_amount', '200.00')->assertJsonPath('data.0.commission_amount', $commission);
    $p2 = $affs->markPaid($affs->requestPayout($this->aff));
    expect((string) $p2->bonus_amount)->toBe('200.00')->and((string) $p2->commission_amount)->toBe($commission);
});

it('isolates a failing affiliate: the others are still awarded', function () {
    $other = Affiliate::create(['name' => 'B', 'email' => 'b-bonus@egy.test', 'password' => 'password', 'status' => AffiliateStatus::APPROVED, 'approved_at' => now()]);
    ($this->sale)(10);
    ($this->sale)(10, $other);
    $this->travel(15)->days();
    AffiliateBonus::creating(function (AffiliateBonus $b) {
        if ($b->affiliate_id === $this->aff->id) {
            throw new RuntimeException('boom');
        }
    });
    expect($this->svc->awardDue())->toBe(1);
    expect(AffiliateBonus::where('affiliate_id', $other->id)->count())->toBe(1)->and(AffiliateBonus::where('affiliate_id', $this->aff->id)->count())->toBe(0);
});

it('posts bonuses and paid payouts missed while auto-posting was off through the ERP "post missing" scan, once', function () {
    AffiliateTier::query()->delete();                                          // bonus only: the payout = the bonus
    $erp = app(\App\Services\Erp\Equity\ErpSettings::class);
    $erp->set('auto_posting_enabled', false);
    ($this->sale)(10);
    $this->travel(15)->days();
    ($this->run)();
    $b = AffiliateBonus::sole();
    $affs = app(AffiliateService::class);
    $p = $affs->markPaid($affs->requestPayout($this->aff), null, 'bank');
    expect(JournalEntry::where('idempotency_key', 'affbonus:'.$b->id)->exists())->toBeFalse()
        ->and(JournalEntry::where('idempotency_key', 'affpayout:'.$p->id)->exists())->toBeFalse();
    $erp->set('auto_posting_enabled', true);
    $bf = app(\App\Services\Erp\Backfill\BackfillService::class);
    expect($bf->missing()['affiliates']['created'])->toBe(2);                  // detect (dry)
    $bf->missing(true);
    $bf->missing(true);                                                       // idempotent
    expect(JournalEntry::where('idempotency_key', 'like', 'affbonus:'.$b->id.'%')->count())->toBe(1)
        ->and(JournalEntry::where('idempotency_key', 'like', 'affpayout:'.$p->id.'%')->count())->toBe(1)
        ->and($bf->missing()['affiliates']['created'])->toBe(0);
    $bal = fn (string $role) => (float) \App\Models\Erp\Account::where('role_key', $role)->value('current_balance');
    expect($bal('affiliate_payable'))->toBe(0.0);                             // accrued by the bonus, cleared by the payout
});
