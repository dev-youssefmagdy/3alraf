<?php

use App\Models\CentralAdmin;
use App\Models\Erp\Account;
use App\Models\Erp\ExchangeRate;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Central\AnomalyService;
use App\Services\Erp\Central\CentralErpService;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->egy = Tenant::where('slug', 'egy')->first();
    $mk = fn (string $slug, string $name, string $cur) => Tenant::create(['slug' => $slug, 'name' => $name, 'country' => 'XX', 'currency_code' => $cur, 'currency_symbol' => $cur, 'currency_rate' => 1,
        'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'UTC', 'is_active' => true]);
    $this->usa = $mk('usa', 'USA Store', 'USD');
    $this->egy2 = $mk('egy2', 'Cairo Two', 'EGP');
    $this->je = app(JournalService::class);
    $this->today = now()->toDateString();
    foreach ([$this->egy, $this->usa, $this->egy2] as $t) {
        app(CoaTemplate::class)->seed($t);
    }
    // post as a tenant (JournalService is tenant-scoped); the central panel then reads across all of them
    $this->tx = fn (Tenant $t, string $dr, string $cr, string $amt, ?string $date = null, array $x = []) => TenantContext::run($t, fn () => $this->je->post(['entry_date' => $date ?? $this->today, 'description' => 'tx'] + ($x['entry'] ?? []),
        [['account' => $dr, 'debit' => $amt] + ($x['dr'] ?? []), ['account' => $cr, 'credit' => $amt] + ($x['cr'] ?? [])]));
    ($this->tx)($this->egy, 'bank', 'owner_capital', '10000');
    ($this->tx)($this->egy, 'receivable', 'sales_revenue', '500');
    ($this->tx)($this->egy, 'rent', 'bank', '100');
    ($this->tx)($this->usa, 'bank', 'owner_capital', '900');
    ($this->tx)($this->usa, 'receivable', 'sales_revenue', '70');
    ($this->tx)($this->usa, 'rent', 'bank', '20');
    ($this->tx)($this->egy2, 'receivable', 'sales_revenue', '30');
    app()->forgetInstance('currentTenant');
    $this->central = 'http://3alraf.test/admin/erp';
    $this->super = CentralAdmin::where('email', 'admin@3alraf.test')->first();
    $this->actingAs($this->super, 'central');
    $this->svc = app(CentralErpService::class);
    $this->table = function (string $html, string $attr) {
        $a = strpos($html, $attr);

        return $a === false ? '' : substr($html, $a, strpos($html, '</table>', $a) - $a);
    };
    $this->row = fn (string $slug) => $this->svc->overview()->first(fn ($r) => $r['tenant']->slug === $slug);
});

it('forbids central users without permission and gates each card by its own permission', function () {
    $plain = CentralAdmin::create(['name' => 'Plain', 'email' => 'plain@3alraf.test', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($plain, 'central');
    foreach (['', '/overview', '/anomalies', '/audit', '/transactions', '/tenants/'.$this->egy->id] as $p) {
        $this->get($this->central.$p)->assertForbidden();
    }
    $role = Role::create(['name' => 'erp-view', 'guard_name' => 'central']);
    $role->givePermissionTo('erp_central.view');
    $viewer = CentralAdmin::create(['name' => 'Viewer', 'email' => 'viewer@3alraf.test', 'password' => 'password', 'is_active' => true]);
    $viewer->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($viewer->fresh(), 'central');
    $this->get($this->central)->assertOk();
    $this->get($this->central.'/overview')->assertForbidden();
    $this->get($this->central.'/audit')->assertForbidden();
    $this->get($this->central.'/overview/export')->assertForbidden();
    // reports-only user sees financial cards but not audit/anomalies
    $role->givePermissionTo('erp_central.reports');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($viewer->fresh(), 'central');
    $this->get($this->central)->assertOk()->assertSee('data-card="overview"', false)->assertDontSee('data-card="anomalies"', false);
    $this->get($this->central.'/overview')->assertOk();
    $this->get($this->central.'/anomalies')->assertForbidden();
    // super admin: hub shows every card and the sidebar entry
    $this->actingAs($this->super, 'central');
    $this->get($this->central)->assertOk()->assertSee('data-card="audit"', false)->assertSee(__('erp_central.menu'));
});

it('shows each tenant with its own numbers, never mixed', function () {
    $e = ($this->row)('egy');
    expect($e['revenue'])->toBe('500.0000')->and($e['expenses'])->toBe('100.0000')->and($e['net'])->toBe('400.0000')->and($e['cash'])->toBe('9900.0000')
        ->and($e['ar'])->toBe('500.0000')->and($e['equity'])->toBe('10400.0000')->and($e['currency'])->toBe('EGP')->and($e['last_posting'])->toBe($this->today);
    $u = ($this->row)('usa');
    expect($u['revenue'])->toBe('70.0000')->and($u['net'])->toBe('50.0000')->and($u['cash'])->toBe('880.0000')->and($u['currency'])->toBe('USD')->and($u['equity'])->toBe('950.0000');
    $page = $this->get($this->central.'/overview')->assertOk()->assertSee('USA Store')->assertSee('Cairo Two')->assertSee('Egypt Store');
    $html = $page->getContent();
    expect($html)->toContain('data-revenue>500.00<')->toContain('data-revenue>70.00<')->toContain('data-revenue>30.00<');
    // filters / sort
    $t = fn (string $qs) => ($this->table)($this->get($this->central.'/overview'.$qs)->getContent(), 'data-overview');
    expect($t('?currency=USD'))->toContain('USA Store')->not->toContain('Cairo Two')->not->toContain('Egypt Store');
    expect($t('?q=cairo'))->toContain('Cairo Two')->not->toContain('USA Store');
    $sorted = $this->svc->overview(null, null, ['sort' => 'revenue', 'dir' => 'desc'])->pluck('name')->values()->all();
    expect($sorted[0])->toBe('Egypt Store');
});

it('groups per currency and never sums EGP with USD', function () {
    $groups = $this->svc->groups($this->svc->overview());
    expect($groups->keys()->all())->toBe(['EGP', 'USD'])
        ->and($groups['EGP']['totals']['revenue'])->toBe('530.0000')   // egy 500 + egy2 30, same currency
        ->and($groups['USD']['totals']['revenue'])->toBe('70.0000')
        ->and($groups['EGP']['tenants']->pluck('name')->sort()->values()->all())->toBe(['Cairo Two', 'Egypt Store'])
        ->and($groups['EGP']['converted'])->toBeNull();
    foreach (['consolidated', 'performance'] as $p) {
        $this->get($this->central.'/'.$p)->assertOk()->assertSee('530.00')->assertSee('70.00')->assertDontSee('600.00')
            ->assertSee($p === 'consolidated' ? __('erp_central.consolidated_view', ['c' => 'EGP']) : __('erp_central.currency_group', ['c' => 'EGP']));
    }
    $this->get($this->central.'/consolidated')->assertSee('data-consolidated="EGP"', false)->assertSee('data-consolidated="USD"', false);
    // conversion needs a stored rate: without one the group says so and shows no converted total
    $page = $this->get($this->central.'/consolidated?convert_to=USD')->assertOk();
    expect($page->getContent())->not->toContain('data-converted="EGP"')->and($page->getContent())->not->toContain('data-converted="USD"');
    $page->assertSee(__('erp_central.no_rate', ['tenants' => 'Cairo Two, Egypt Store', 'from' => 'EGP', 'to' => 'USD']), false);
    ExchangeRate::create(['tenant_id' => null, 'from_currency' => 'USD', 'to_currency' => 'EGP', 'rate' => '50', 'effective_date' => '2020-01-01']);
    ExchangeRate::create(['tenant_id' => $this->egy2->id, 'from_currency' => 'EGP', 'to_currency' => 'USD', 'rate' => '0.04', 'effective_date' => '2020-01-01']); // tenant override
    $conv = $this->svc->groups($this->svc->overview(), 'USD');
    // egy via inverse of the global rate (1/50 = 0.02): 500 -> 10 ; egy2 via its own 0.04: 30 -> 1.2
    expect($conv['EGP']['converted']['totals']['revenue'])->toBe('11.2000')->and($conv['EGP']['converted']['complete'])->toBeTrue()
        ->and($conv['EGP']['converted']['rates'][$this->egy2->id]['scope'])->toBe('tenant')->and($conv['EGP']['converted']['rates'][$this->egy->id]['scope'])->toBe('global')
        ->and($conv['EGP']['totals']['revenue'])->toBe('530.0000'); // the native subtotal is untouched
    $this->get($this->central.'/consolidated?convert_to=USD')->assertSee('data-converted="EGP"', false)->assertSee('11.20');
});

it('drills into the right tenant, read-only, and refuses an unknown id', function () {
    $page = $this->get($this->central.'/tenants/'.$this->usa->id)->assertOk()->assertSee('USA Store')->assertSee('USD')->assertSee('data-tenant-banner', false);
    $html = $page->getContent();
    expect($html)->toContain('70.00')->and($html)->not->toContain('10,000.00')->and($html)->not->toContain('500.00');
    $this->get($this->central.'/tenants/'.$this->egy->id)->assertOk()->assertSee('Egypt Store')->assertSee('10,000.00');
    $this->get($this->central.'/tenants/999999')->assertNotFound();
    // central drill-down leaves no tenant context behind
    expect(current_tenant())->toBeNull();
});

it('keeps tenant admins out of the central ERP routes', function () {
    $this->app['auth']->forgetGuards();
    $owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($owner, 'tenant');
    $this->app['auth']->guard('central')->logout();
    foreach (['', '/overview', '/anomalies', '/audit', '/tenants/'.$this->egy->id] as $p) {
        expect($this->get($this->central.$p)->status())->not->toBe(200);
    }
    expect($this->get('http://egy.3alraf.test/admin/erp/overview')->status())->toBe(404);
});

it('lists journal entries across tenants with filters and a read-only detail', function () {
    $usaEntry = DB::table('erp_journal_entries')->where('tenant_id', $this->usa->id)->where('total', '70.0000')->first();
    $this->get($this->central.'/transactions')->assertOk();
    $tbl = fn (string $qs) => ($this->table)($this->get($this->central.'/transactions'.$qs)->getContent(), 'data-entries');
    expect($tbl('?tenant_id='.$this->usa->id))->toContain('USA Store')->not->toContain('Egypt Store')->not->toContain('Cairo Two');
    expect($tbl(''))->toContain('USA Store')->toContain('Egypt Store')->toContain('Cairo Two');
    $this->get($this->central.'/transactions?account=6100&tenant_id='.$this->usa->id)->assertOk();
    expect($tbl('?status=draft'))->toContain(__('erp_central.empty'))->not->toContain('USA Store');
    expect($tbl('?from='.now()->addDay()->toDateString()))->toContain(__('erp_central.empty'));
    $rent = Account::withoutGlobalScopes()->where('tenant_id', $this->usa->id)->where('role_key', 'rent')->value('code');
    $r = $this->get($this->central.'/transactions?tenant_id='.$this->usa->id.'&account='.$rent)->assertOk();
    expect(substr_count(($this->table)($r->getContent(), 'data-entries'), '/erp/transactions/'))->toBe(1);
    $this->get($this->central.'/transactions/'.$usaEntry->id)->assertOk()->assertSee('USA Store')->assertSee('USD')->assertSee('70.00');
    $this->get($this->central.'/transactions/99999999')->assertNotFound();
});

it('monitors receivables, payables, inventory and periods per tenant', function () {
    $cust = DB::table('customers')->insertGetId(['tenant_id' => $this->usa->id, 'name' => 'Big Debtor', 'email' => 'bd@x.test', 'phone' => '01000000777', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    ($this->tx)($this->usa, 'receivable', 'sales_revenue', '300', null, ['dr' => ['party_type' => 'customer', 'party_id' => $cust]]);
    $p = $this->svc->parties();
    expect($p[$this->usa->id]['ar']['total'])->toBe('300.0000')->and($p[$this->usa->id]['ar']['top'][0]['name'])->toBe('Big Debtor')->and($p[$this->egy->id]['ar']['total'])->toBe('0.0000');
    $this->get($this->central.'/parties?ageing=1')->assertOk()->assertSee('Big Debtor')->assertSee('data-ageing="ar"', false);
    $inv = $this->svc->inventory();
    expect($inv->count())->toBe(3)->and($inv->firstWhere('id', $this->usa->id)['ledger'])->toBe('0.0000');
    ($this->tx)($this->usa, 'inventory', 'bank', '40');
    expect($this->svc->inventory()->firstWhere('id', $this->usa->id)['difference'])->toBe('40.0000');
    $this->get($this->central.'/inventory')->assertOk()->assertSee('USA Store')->assertSee(__('erp_central.mismatch'));
    DB::table('erp_audit_logs')->insert(['tenant_id' => $this->usa->id, 'action' => 'period.reopened', 'subject_type' => 'period', 'subject_id' => 1, 'meta' => json_encode(['name' => 'Jan', 'reason' => 'fix']), 'created_at' => now()]);
    $this->get($this->central.'/periods')->assertOk()->assertSee('data-reopen', false)->assertSee('period.reopened')->assertSee('fix');
    $this->get($this->central.'/periods?tenant_id='.$this->usa->id)->assertOk();
});

it('detects seeded problems per tenant and leaves healthy tenants clean', function () {
    expect(app(AnomalyService::class)->detect()->where('code', '!=', 'old_draft')->count())->toBe(0);
    // unbalanced posted entry (bypasses the service on purpose)
    $bad = DB::table('erp_journal_entries')->insertGetId(['tenant_id' => $this->usa->id, 'number' => 'JE-900001', 'status' => 'posted', 'type' => 'manual', 'entry_date' => $this->today, 'currency' => 'USD', 'total' => 10, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $acc = Account::withoutGlobalScopes()->where('tenant_id', $this->usa->id)->where('role_key', 'rent')->value('id');
    DB::table('erp_journal_lines')->insert(['tenant_id' => $this->usa->id, 'entry_id' => $bad, 'account_id' => $acc, 'debit' => 10, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()]);
    // stale draft
    TenantContext::run($this->usa, fn () => $this->je->draft(['entry_date' => $this->today, 'description' => 'old'], [['account' => 'rent', 'debit' => '5'], ['account' => 'bank', 'credit' => '5']]));
    DB::table('erp_journal_entries')->where('tenant_id', $this->usa->id)->where('status', 'draft')->update(['created_at' => now()->subDays(20)]);
    // failed posting, reopened period
    DB::table('erp_audit_logs')->insert([['tenant_id' => $this->usa->id, 'action' => 'posting.failed', 'created_at' => now()], ['tenant_id' => $this->usa->id, 'action' => 'year.reopened', 'created_at' => now()]]);
    // backdated: posted long after its period ended
    $old = ($this->tx)($this->usa, 'rent', 'bank', '1', now()->subDays(60)->toDateString());
    DB::table('erp_journal_entries')->where('id', $old->id)->update(['posted_at' => now()]);
    // negative cash (egy2 has no bank money) + control mismatch (usa receivable cache drift) + large manual (egy2)
    ($this->tx)($this->egy2, 'rent', 'bank', '75');
    Account::withoutGlobalScopes()->where('tenant_id', $this->usa->id)->where('role_key', 'receivable')->update(['current_balance' => 9999]);
    ($this->tx)($this->egy2, 'receivable', 'sales_revenue', '250000');
    DB::table('erp_journal_entries')->where('tenant_id', $this->egy2->id)->where('total', '250000.0000')->update(['type' => 'manual']);
    $d = app(AnomalyService::class)->detect();
    $codes = fn (Tenant $t) => $d->where('tenant_id', $t->id)->pluck('code')->sort()->values()->all();
    expect($codes($this->usa))->toBe(collect(['backdated_entry', 'control_mismatch', 'old_draft', 'period_reopened', 'posting_failed', 'unbalanced_entry'])->sort()->values()->all())
        ->and($codes($this->egy2))->toBe(['large_manual', 'negative_cash'])
        ->and($codes($this->egy))->toBe([]);
    expect($d->first()['severity'])->toBe('high')->and($d->firstWhere('code', 'unbalanced_entry')['entry_id'])->toBe($bad)->and($d->firstWhere('code', 'negative_cash')['detail'])->not->toBeEmpty();
    // page + filters + csv + tenant-side use of the same service
    $page = $this->get($this->central.'/anomalies')->assertOk()->assertSee(__('erp_central.an.unbalanced_entry')[0]);
    expect($page->getContent())->toContain('data-code="negative_cash"')->toContain('data-code="large_manual"');
    $this->get($this->central.'/anomalies?severity=low&tenant_id='.$this->usa->id)->assertSee('data-code="old_draft"', false)->assertDontSee('data-code="unbalanced_entry"', false)->assertDontSee('data-code="large_manual"', false);
    $this->get($this->central.'/anomalies?code=negative_cash')->assertSee('data-tenant="'.$this->egy2->id.'"', false)->assertDontSee('data-tenant="'.$this->usa->id.'"', false);
    expect(app(AnomalyService::class)->detect([$this->usa->id])->pluck('tenant_id')->unique()->all())->toBe([$this->usa->id]);
    $csv = $this->get($this->central.'/anomalies/export?tenant_id='.$this->usa->id);
    $csv->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')->and($csv->streamedContent())->toContain('unbalanced_entry')->not->toContain('negative_cash');
    expect($this->svc->overview()->firstWhere('id', $this->usa->id)['anomalies'])->toBe(array_sum(app(AnomalyService::class)->countsByTenant([$this->usa->id])));
    // deep check runs (ledger vs Net Profit report) without breaking
    $this->get($this->central.'/anomalies?deep=1')->assertOk();
    // tenant hub shows the alerts panel of its own tenant only
    $this->app['auth']->guard('central')->logout();
    $owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($owner, 'tenant');
    $this->get('http://egy.3alraf.test/admin/erp')->assertOk()->assertSee(__('erp_central.alerts.title'))->assertSee(__('erp_central.alerts.none'))->assertDontSee(__('erp_central.an.unbalanced_entry')[0]);
});

it('views and filters the audit log across tenants', function () {
    DB::table('erp_audit_logs')->insert([
        ['tenant_id' => $this->egy->id, 'action' => 'period.closed', 'subject_type' => 'period', 'subject_id' => 3, 'user_id' => 7, 'created_at' => now()->subDays(3)],
        ['tenant_id' => $this->usa->id, 'action' => 'period.reopened', 'subject_type' => 'period', 'subject_id' => 4, 'user_id' => 9, 'created_at' => now()],
    ]);
    $t = fn (string $qs) => ($this->table)($this->get($this->central.'/audit'.$qs)->getContent(), 'data-audit');
    expect($t(''))->toContain('period.closed')->toContain('period.reopened')->toContain('USA Store');
    expect($t('?tenant_id='.$this->egy->id.'&action=period.closed'))->toContain('period.closed')->not->toContain('period.reopened');
    expect($t('?action=period.reopened'))->toContain('period.reopened')->not->toContain('period.closed');
    expect($t('?user_id=9'))->toContain('period.reopened')->not->toContain('period.closed');
    expect($t('?from='.now()->subDay()->toDateString()))->toContain('period.reopened')->not->toContain('period.closed');
    expect($t('?to='.now()->subDays(2)->toDateString()))->toContain('period.closed')->not->toContain('period.reopened');
});

it('exports the tenant overview as csv with per-tenant rows and safe cells', function () {
    $this->egy->update(['name' => '=cmd Store']);
    $res = $this->get($this->central.'/overview/export');
    $res->assertOk();
    $body = $res->streamedContent();
    expect($res->headers->get('content-type'))->toContain('text/csv')->and($body)->toStartWith("\xEF\xBB\xBF")
        ->and($body)->toContain('tenant,currency,revenue')->toContain('"USA Store",USD,70.0000,20.0000,50.0000')->toContain("\"'=cmd Store\",EGP,500.0000,100.0000,400.0000");
    expect($this->get($this->central.'/overview/export?currency=USD')->streamedContent())->not->toContain('Cairo Two');
});

it('aggregates with a bounded number of queries regardless of tenant count', function () {
    $count = function (string $url) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };
    foreach (['overview', 'consolidated', 'anomalies', 'inventory'] as $warm) {
        $this->get($this->central.'/'.$warm)->assertOk(); // warm caches (permissions, translations) so counts are comparable
    }
    $before = ['o' => $count($this->central.'/overview'), 'g' => $count($this->central.'/consolidated'), 'a' => $count($this->central.'/anomalies'), 'i' => $count($this->central.'/inventory')];
    foreach (range(1, 5) as $i) {
        $t = Tenant::create(['slug' => 'x'.$i, 'name' => 'X'.$i, 'country' => 'XX', 'currency_code' => $i % 2 ? 'EGP' : 'USD', 'currency_symbol' => 'x', 'currency_rate' => 1, 'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'UTC', 'is_active' => true]);
        app(CoaTemplate::class)->seed($t);
        ($this->tx)($t, 'receivable', 'sales_revenue', '10');
        app()->forgetInstance('currentTenant');
    }
    $after = ['o' => $count($this->central.'/overview'), 'g' => $count($this->central.'/consolidated'), 'a' => $count($this->central.'/anomalies'), 'i' => $count($this->central.'/inventory')];
    expect($after)->toBe($before)->and($after['o'])->toBeLessThan(40)->and($after['a'])->toBeLessThan(40);
});

it('lets a tenant store exchange rates that the central view uses', function () {
    $this->app['auth']->guard('central')->logout();
    $owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($owner, 'tenant');
    app()->instance('currentTenant', $this->egy);
    $this->post('http://egy.3alraf.test/admin/erp/settings/rates', ['from_currency' => 'egp', 'to_currency' => 'usd', 'rate' => '0.02', 'effective_date' => '2026-01-01'])->assertRedirect();
    $this->post('http://egy.3alraf.test/admin/erp/settings/rates', ['from_currency' => 'egp', 'to_currency' => 'egp', 'rate' => '1', 'effective_date' => '2026-01-01'])->assertSessionHasErrors('to_currency');
    $r = ExchangeRate::first();
    expect($r->tenant_id)->toBe($this->egy->id)->and($r->from_currency)->toBe('EGP')->and($r->to_currency)->toBe('USD');
    $this->get('http://egy.3alraf.test/admin/erp/settings')->assertOk()->assertSee('1 EGP = 0.02 USD');
    app()->forgetInstance('currentTenant');
    $conv = $this->svc->groups($this->svc->overview(), 'USD');
    expect($conv['EGP']['converted']['missing'])->toBe(['Cairo Two'])->and($conv['EGP']['converted']['totals']['revenue'])->toBe('10.0000')->and($conv['EGP']['converted']['complete'])->toBeFalse();
    app()->instance('currentTenant', $this->egy);
    $this->delete('http://egy.3alraf.test/admin/erp/settings/rates/'.$r->id)->assertRedirect();
    expect(ExchangeRate::count())->toBe(0);
});
