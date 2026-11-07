<?php

/*
 * QA E2E — ERP permissions matrix, localisation / RTL, panel connection, robustness (XSS, CSRF, long input, CSV).
 * "QA-BUG-xxx" tests encode the CORRECT behaviour and fail until fixed (docs/qa/QA_TEST_REPORT.md).
 * Run alone: php artisan test tests/Feature/QaE2eErpI18nPermsTest.php
 */

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\Owner;
use App\Models\Supplier;
use App\Models\Admin;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use Database\Seeders\FoundationSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    app(CoaTemplate::class)->seed();
    $this->a = '/admin';
    $this->e = $this->a.'/erp';
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->je = app(JournalService::class);
    $this->acc = fn (string $role) => (int) Account::where('role_key', $role)->value('id');
});

/** Staff member holding exactly $perms (custom role, like the role editor creates). */
function qaiUser(array $perms, string $name = 'qa'): Admin
{
    $role = Role::create(['name' => $name, 'guard_name' => 'admin']);
    $role->givePermissionTo($perms);
    $u = Admin::query()->create(['name' => 'QA '.$name, 'email' => $name.'@egy.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $u->fresh();
}

function qaiPost(string $dr, string $cr, string $amt, array $e = []): JournalEntry
{
    return app(JournalService::class)->post(['entry_date' => now()->toDateString(), 'description' => 'seed'] + $e, [['account' => $dr, 'debit' => $amt], ['account' => $cr, 'credit' => $amt]]);
}

/** All flashed validation messages (flat list), whatever shape the session holds them in. */
function qaiErrs(): array
{
    $e = session('errors');
    if ($e instanceof \Illuminate\Support\ViewErrorBag) {
        return $e->getBag('default')->all();
    }
    $out = [];
    array_walk_recursive($e, function ($v, $k) use (&$out) {
        if (is_string($v) && $k !== 'format') {
            $out[] = $v;
        }
    });

    return $out;
}

/** Hub card hrefs (hub cards are <a class="group rounded-xl ..."). */
function qaiCards(string $html): array
{
    preg_match_all('#<a href="([^"]+)" class="group rounded-xl#', $html, $m);

    return array_map(fn ($u) => preg_replace('#^https?://[^/]+#', '', html_entity_decode($u)), $m[1]);
}

/* ---------------------------------------------------------------- permission matrix */

it('PERMS reports-only accountant: report pages work, exports/journals/actions forbidden, export buttons hidden', function () {
    qaiPost('cash', 'sales_revenue', '100');
    $u = qaiUser(['erp.reports.view'], 'reports');
    $this->actingAs($u, 'admin');
    $this->get($this->e.'/reports')->assertOk()->assertDontSee('/reports/branch-profitability"', false);
    foreach (['trial-balance', 'profit-loss', 'balance-sheet', 'cash-flow', 'sales', 'tax'] as $r) {
        $this->get($this->e."/reports/$r")->assertOk()->assertDontSee('/export', false);
    }
    $this->get($this->e.'/reports/branch-profitability')->assertForbidden();
    $this->get($this->e.'/reports/trial-balance/export')->assertForbidden();
    $this->get($this->e.'/journals')->assertForbidden();
    $this->get($this->e.'/accounts')->assertForbidden();
    $this->post($this->e.'/journals', [])->assertForbidden();
    $this->get($this->e.'/settings')->assertForbidden();
    $this->get($this->e.'/dashboard')->assertForbidden();
});

it('QA-BUG-010 pages do not render links to journal entries the user is not allowed to open (reports-only user, general ledger)', function () {
    $e = qaiPost('cash', 'sales_revenue', '100');
    $u = qaiUser(['erp.reports.view'], 'reports');
    $this->actingAs($u, 'admin');
    $html = $this->get($this->e.'/reports/general-ledger?account_id='.($this->acc)('cash').'&from='.now()->startOfMonth()->toDateString())->assertOk()->getContent();
    expect($html)->toContain($e->number);
    $this->get($this->e.'/journals/'.$e->id)->assertForbidden();
    expect($html)->not->toContain('/erp/journals/'.$e->id.'"');
});

it('QA-BUG-010b dashboard-only user is not shown "recent entries" links that 403', function () {
    $e = qaiPost('cash', 'sales_revenue', '100');
    $u = qaiUser(['erp.dashboard.view'], 'dash');
    $this->actingAs($u, 'admin');
    $html = $this->get($this->e.'/dashboard')->assertOk()->getContent();
    $this->get($this->e.'/journals/'.$e->id)->assertForbidden();
    expect($html)->not->toContain('/erp/journals/'.$e->id.'"');
});

it('PERMS journals.create without journals.post: can draft, cannot post (form option hidden, 403 on direct post)', function () {
    $u = qaiUser(['erp.dashboard.view', 'erp.journals.view', 'erp.journals.create'], 'maker');
    $this->actingAs($u, 'admin');
    $form = $this->get($this->e.'/journals/create')->assertOk();
    $form->assertDontSee('name="post_now"', false);
    $lines = [['account_id' => ($this->acc)('rent'), 'debit' => 10], ['account_id' => ($this->acc)('cash'), 'credit' => 10]];
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => 'maker', 'type' => 'manual', 'post_now' => 1, 'lines' => $lines])->assertForbidden();
    expect(JournalEntry::count())->toBe(0);
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => 'maker', 'type' => 'manual', 'lines' => $lines])->assertRedirect();
    $d = JournalEntry::first();
    expect($d->status)->toBe('draft');
    $show = $this->get($this->e."/journals/{$d->id}")->assertOk();
    $show->assertDontSee('/journals/'.$d->id.'/post"', false)->assertSee('name="_method" value="DELETE"', false);
    $this->post($this->e."/journals/{$d->id}/post")->assertForbidden();
    expect($d->fresh()->status)->toBe('draft');
    // hub: only the cards this user may open
    $cards = qaiCards($this->get($this->e)->assertOk()->getContent());
    expect($cards)->toBe(['/admin/erp/journals', '/admin/erp/dashboard']);
    // checker with post permission posts it
    $checker = qaiUser(['erp.journals.view', 'erp.journals.post'], 'checker');
    $this->actingAs($checker, 'admin');
    $this->get($this->e."/journals/{$d->id}")->assertSee('/journals/'.$d->id.'/post"', false)->assertDontSee('name="_method" value="DELETE"', false);
    $this->post($this->e."/journals/{$d->id}/post")->assertSessionHasNoErrors();
    expect($d->fresh()->status)->toBe('posted');
    $this->get($this->e.'/journals/'.$d->id)->assertDontSee('/reverse"', false);
    $this->post($this->e."/journals/{$d->id}/reverse", ['reason' => 'x'])->assertForbidden();
});

it('PERMS view-only users never see action forms (accounts, parties, periods, profits, branches)', function () {
    $c = Customer::create(['name' => 'C', 'email' => 'c@x.com', 'phone' => '1055500111', 'password' => 'secret-123', 'is_active' => true]);
    $s = Supplier::create(['name' => 'S', 'is_active' => true]);
    \App\Models\Erp\Owner::create(['name' => 'O', 'ownership_percent' => 100, 'is_eligible' => true]);
    qaiPost('cash', 'sales_revenue', '1000');
    $dist = app(\App\Services\Erp\Equity\ProfitDistributionService::class)->create(['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(), 'acknowledge_open' => true], $this->owner->id);
    $u = qaiUser(['erp.dashboard.view', 'erp.accounts.view', 'erp.periods.view', 'erp.profits.view', 'erp.branches.view'], 'viewer');
    $this->actingAs($u, 'admin');
    $this->get($this->e.'/accounts')->assertOk()->assertDontSee('erp/accounts" ', false)->assertDontSee('name="code"', false)->assertDontSee('/restore"', false);
    $this->post($this->e.'/accounts', ['code' => 'X', 'name_en' => 'X', 'type' => 'asset'])->assertForbidden();
    foreach (["customer/{$c->id}", "supplier/{$s->id}"] as $p) {
        $this->get($this->e."/parties/$p")->assertOk()->assertDontSee('/settle"', false)->assertDontSee('/note"', false)->assertDontSee('/opening"', false)->assertDontSee('/apply-advance"', false);
        $this->post($this->e."/parties/$p/settle", ['amount' => 1, 'account' => 'cash', 'ref' => 'x'])->assertForbidden();
    }
    $this->get($this->e.'/periods')->assertOk()->assertDontSee('/close"', false)->assertDontSee('/reopen"', false);
    $this->post($this->e.'/periods/'.\App\Models\Erp\Period::value('id').'/close')->assertForbidden();
    $this->get($this->e.'/profits')->assertOk()->assertDontSee('name="acknowledge_open"', false);
    $this->get($this->e."/profits/{$dist->id}")->assertOk()->assertDontSee('/approve"', false)->assertDontSee('/cancel"', false);
    $this->post($this->e."/profits/{$dist->id}/approve")->assertForbidden();
    $this->post($this->e."/profits/{$dist->id}/cancel")->assertForbidden();
    $this->get($this->e.'/branches')->assertOk()->assertDontSee('/branches/transfer"', false);
    $this->post($this->e.'/branches/transfer', ['from_branch' => 1, 'to_branch' => 2, 'amount' => 1])->assertForbidden();
    // owners & assets & settings & migration are not reachable
    foreach (['/owners', '/assets', '/settings', '/migration', '/journals'] as $p) {
        $this->get($this->e.$p)->assertForbidden();
    }
    $cards = qaiCards($this->get($this->e)->assertOk()->getContent());
    expect($cards)->toEqualCanonicalizing(['/admin/erp/accounts', '/admin/erp/periods', '/admin/erp/parties/customer', '/admin/erp/parties/supplier', '/admin/erp/cash-bank', '/admin/erp/profits', '/admin/erp/branches', '/admin/erp/dashboard']);
});

it('PERMS roles editor lists the ERP permissions for the owner', function () {
    $this->actingAs($this->owner, 'admin');
    $html = $this->get($this->a.'/roles/create')->assertOk()->getContent();
    foreach (['erp.dashboard.view', 'erp.journals.post', 'erp.journals.reverse', 'erp.profits.distribute', 'erp.periods.reopen', 'erp.settings.manage', 'erp.reports.export'] as $p) {
        expect($html)->toContain('value="'.$p.'"');
    }
    // creating a role through the editor with ERP perms works
    $this->postJson($this->a.'/roles', ['name' => 'Accountant', 'permissions' => ['erp.dashboard.view', 'erp.reports.view']])->assertOk();
    expect(Role::where('name', 'Accountant')->first()?->hasPermissionTo('erp.reports.view', 'admin'))->toBeTrue();
});

/* ---------------------------------------------------------------- localisation / RTL / panel connection */

it('I18N admin locale switch: ar = dir rtl + Arabic ERP labels, en = dir ltr + English labels, invalid code falls back', function () {
    $this->actingAs($this->owner, 'admin');
    $this->get($this->a.'/locale/ar')->assertRedirect();
    $ar = $this->get($this->e)->assertOk()->getContent();
    expect($ar)->toContain('dir="rtl"')->toContain('lang="ar"')->toContain(e(__('erp.menu', [], 'ar')));
    $this->get($this->a.'/locale/en');
    $en = $this->get($this->e)->assertOk()->getContent();
    expect($en)->toContain('dir="ltr"')->toContain('lang="en"')->toContain(e(__('erp.menu', [], 'en')));
    $this->get($this->a.'/locale/xx');
    $this->get($this->e)->assertOk();
    // numbers formatted with thousands separators in both locales
    qaiPost('cash', 'sales_revenue', '1234567.5');
    foreach (['ar', 'en'] as $l) {
        $this->get($this->a.'/locale/'.$l);
        $this->get($this->e.'/reports/trial-balance')->assertOk()->assertSee('1,234,567.50');
    }
});

it('QA-BUG-011 Arabic admin: ERP validation messages are fully Arabic (date/between/different rules and field names are not English)', function () {
    $this->actingAs($this->owner, 'admin');
    $this->get($this->a.'/locale/ar');
    $this->get($this->e.'/journals/create');
    $this->post($this->e.'/journals', ['entry_date' => '2026-02-30', 'description' => 'x', 'type' => 'manual', 'lines' => []]);
    $errs = qaiErrs();
    $this->get($this->e.'/periods?year=1999');
    $errs = array_merge($errs, qaiErrs());
    $this->post($this->e.'/settings/rates', ['from_currency' => 'EGP', 'to_currency' => 'EGP', 'rate' => 1, 'effective_date' => 'x']);
    $errs = array_merge($errs, qaiErrs());
    expect(count($errs))->toBeGreaterThan(3);
    $english = array_values(array_filter($errs, fn ($m) => preg_match('/\b(The|field|must|valid|between)\b/', $m) || preg_match('/\b(entry_date|entry date|year|to_currency|effective_date|reason|lines)\b/', $m)));
    expect($english)->toBe([]);
});

it('PANEL sidebar shows the ERP item, highlighted on ERP pages; hub cards link to working pages', function () {
    $this->actingAs($this->owner, 'admin');
    $this->get($this->a.'/locale/en');
    $hub = $this->get($this->e)->assertOk()->getContent();
    expect($hub)->toMatch('#<a href="'.preg_quote(url('/admin/erp'), '#').'" class="side-link[^"]*\bactive\b#');
    $this->get($this->e.'/journals')->assertOk()->assertSee(__('erp.menu'));
    $j = $this->get($this->e.'/reports/profit-loss')->getContent();
    expect($j)->toMatch('#<a href="'.preg_quote(url('/admin/erp'), '#').'" class="side-link[^"]*\bactive\b#');
    $cards = qaiCards($hub);
    expect(count($cards))->toBe(count(\App\Http\Controllers\Admin\Erp\HubController::CARDS));
    foreach ($cards as $url) {
        $this->get(''.$url)->assertOk();
    }
});

/* ---------------------------------------------------------------- robustness: CSRF, XSS, long text, CSV */

it('ROBUST every ERP form on every page carries a CSRF token', function () {
    $this->actingAs($this->owner, 'admin');
    $c = Customer::create(['name' => 'C', 'email' => 'c@x.com', 'phone' => '1055500112', 'password' => 'secret-123', 'is_active' => true]);
    $o = Owner::create(['name' => 'O', 'ownership_percent' => 10, 'is_eligible' => true]);
    $d = app(JournalService::class)->draft(['entry_date' => now()->toDateString(), 'description' => 'd'], [['account' => 'rent', 'debit' => '5'], ['account' => 'cash', 'credit' => '5']]);
    $bad = [];
    foreach (['/accounts', '/journals/create', "/journals/{$d->id}", '/periods', "/parties/customer/{$c->id}", '/owners', "/owners/{$o->id}", '/assets', '/profits', '/branches', '/settings', '/migration'] as $p) {
        $html = $this->get($this->e.$p)->assertOk()->getContent();
        preg_match_all('#<form\b[^>]*method="POST"[^>]*>(.*?)</form>#is', $html, $forms);
        foreach ($forms[1] as $i => $body) {
            if (! str_contains($body, 'name="_token"')) {
                $bad[] = "$p form#$i";
            }
        }
    }
    expect($bad)->toBe([]);
});

it('ROBUST XSS payloads in account name, journal description/memo, owner, supplier, asset and note text are escaped on every page and neutralised in CSV', function () {
    $x = '<script>alert("qa")</script><img src=x onerror=alert(1)>';
    $f = '=HYPERLINK("http://evil","x")';
    $this->actingAs($this->owner, 'admin');
    $this->get($this->a.'/locale/en');
    $grp = Account::where('type', 'expense')->where('is_group', true)->value('id');
    $this->post($this->e.'/accounts', ['code' => 'QAX1', 'name_en' => $x, 'name_ar' => $x, 'description' => $x, 'type' => 'expense', 'parent_id' => $grp])->assertSessionHasNoErrors();
    $acc = Account::where('code', 'QAX1')->first();
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => $x.$f, 'type' => 'manual', 'post_now' => 1,
        'lines' => [['account_id' => $acc->id, 'debit' => 10, 'memo' => $x], ['account_id' => ($this->acc)('cash'), 'credit' => 10, 'memo' => $f]]])->assertSessionHasNoErrors();
    $e = JournalEntry::latest('id')->first();
    $this->post($this->e.'/owners', ['name' => $x, 'ownership_percent' => 50, 'is_eligible' => 1])->assertSessionHasNoErrors();
    $s = Supplier::create(['name' => $x, 'is_active' => true]);
    $this->post($this->e."/parties/supplier/{$s->id}/note", ['kind' => 'debit_note', 'amount' => 5, 'ref' => 'n1', 'description' => $x])->assertSessionHasNoErrors();
    $this->post($this->e.'/assets', ['name' => $x, 'category' => $x, 'acquisition_date' => now()->toDateString(), 'cost' => 100, 'useful_life_months' => 10, 'method' => 'straight_line', 'paid_from' => 'cash', 'note' => $x])->assertSessionHasNoErrors();
    $o = Owner::first();
    $asset = \App\Models\Erp\FixedAsset::first();
    $bad = [];
    $pages = ['/accounts', '/journals', "/journals/{$e->id}", '/journals/create', '/dashboard', '/owners', "/owners/{$o->id}", "/parties/supplier/{$s->id}", '/parties/supplier', '/parties/supplier/ageing',
        '/assets', "/assets/{$asset->id}", '/profits', '/settings', '/reports/trial-balance', '/reports/profit-loss', '/reports/general-ledger?account_id='.$acc->id, '/reports/journal-entries', '/reports/expenses', '/reports/payables', '/reports/owner-equity'];
    foreach ($pages as $p) {
        $html = $this->get($this->e.$p)->assertOk()->getContent();
        if (str_contains($html, '<script>alert("qa")') || str_contains($html, '<img src=x onerror')) {
            $bad[] = $p;
        }
    }
    expect($bad)->toBe([]);
    $csv = $this->get($this->e.'/reports/journal-entries/export')->assertOk()->getContent();
    expect($csv)->not->toMatch('/(^|[,\n])"?=HYPERLINK/');
    $csv2 = $this->get($this->e.'/reports/general-ledger/export?account_id='.($this->acc)('cash'))->assertOk()->getContent();
    expect($csv2)->not->toMatch('/(^|[,\n])"?=HYPERLINK/');
});

it('ROBUST very long text inputs: at the limit accepted and rendered, over the limit = validation error (no 500)', function () {
    $this->actingAs($this->owner, 'admin');
    $lines = [['account_id' => ($this->acc)('rent'), 'debit' => 10, 'memo' => str_repeat('م', 300)], ['account_id' => ($this->acc)('cash'), 'credit' => 10]];
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => str_repeat('ع', 500), 'type' => 'manual', 'lines' => $lines])->assertSessionHasNoErrors();
    $this->get($this->e.'/journals/'.JournalEntry::latest('id')->value('id'))->assertOk();
    $this->get($this->e.'/journals')->assertOk();
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => str_repeat('x', 501), 'type' => 'manual', 'lines' => $lines])->assertSessionHasErrors('description');
    $lines[0]['memo'] = str_repeat('x', 301);
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => 'ok', 'type' => 'manual', 'lines' => $lines])->assertSessionHasErrors('lines.0.memo');
    $this->post($this->e.'/accounts', ['code' => str_repeat('9', 33), 'name_en' => str_repeat('n', 151), 'name_ar' => 'x', 'type' => 'asset', 'description' => str_repeat('d', 501)])->assertSessionHasErrors(['code', 'name_en', 'description']);
    $this->post($this->e.'/owners', ['name' => str_repeat('o', 121), 'ownership_percent' => 1])->assertSessionHasErrors('name');
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => 'many lines', 'type' => 'manual', 'post_now' => 1,
        'lines' => array_merge(array_fill(0, 200, ['account_id' => ($this->acc)('rent'), 'debit' => '0.01']), [['account_id' => ($this->acc)('cash'), 'credit' => '2.00']])])->assertSessionHasNoErrors();
    expect(JournalEntry::latest('id')->first()->lines()->count())->toBe(201);
});

it('ROBUST repeated (concurrent-ish) receipts with the same form ref post exactly once; different refs post separately', function () {
    $this->actingAs($this->owner, 'admin');
    $c = Customer::create(['name' => 'C', 'email' => 'c@x.com', 'phone' => '1055500113', 'password' => 'secret-123', 'is_active' => true]);
    foreach (range(1, 5) as $i) {
        $this->post($this->e."/parties/customer/{$c->id}/settle", ['amount' => '40', 'account' => 'cash', 'ref' => 'same-ref']);
    }
    expect((float) Account::where('role_key', 'cash')->value('current_balance'))->toBe(40.0);
    $this->post($this->e."/parties/customer/{$c->id}/settle", ['amount' => '40', 'account' => 'cash', 'ref' => 'other-ref']);
    expect((float) Account::where('role_key', 'cash')->value('current_balance'))->toBe(80.0);
});

it('QA-BUG-012 re-submitting a receipt with the same ref but a DIFFERENT amount is reported to the user, not silently ignored', function () {
    $this->actingAs($this->owner, 'admin');
    $c = Customer::create(['name' => 'C', 'email' => 'c@x.com', 'phone' => '1055500114', 'password' => 'secret-123', 'is_active' => true]);
    $this->post($this->e."/parties/customer/{$c->id}/settle", ['amount' => '40', 'account' => 'cash', 'ref' => 'r-1'])->assertSessionHasNoErrors();
    $this->post($this->e."/parties/customer/{$c->id}/settle", ['amount' => '90', 'account' => 'cash', 'ref' => 'r-1'])->assertSessionHasErrors();
});

it('QA-BUG-013 double-submitting the manual journal form (post_now) does not create two posted entries', function () {
    $this->actingAs($this->owner, 'admin');
    $html = $this->get($this->e.'/journals/create')->assertOk()->getContent();
    preg_match('/name="(ref|idempotency_key|_idem[a-z_]*)" value="([^"]+)"/', $html, $tok);
    $payload = ['entry_date' => now()->toDateString(), 'description' => 'double', 'type' => 'manual', 'post_now' => 1,
        'lines' => [['account_id' => ($this->acc)('rent'), 'debit' => 10], ['account_id' => ($this->acc)('cash'), 'credit' => 10]]] + ($tok ? [$tok[1] => $tok[2]] : []);
    $this->post($this->e.'/journals', $payload);
    $this->post($this->e.'/journals', $payload);
    expect(JournalEntry::where('description', 'double')->where('status', 'posted')->count())->toBe(1);
});
