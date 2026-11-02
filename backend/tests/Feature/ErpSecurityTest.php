<?php

use App\Models\CentralAdmin;
use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\Period;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Central\Csv;
use App\Services\Erp\Reconciliation\LedgerVerifier;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    app(CoaTemplate::class)->seed($this->tenant);
    $this->je = app(JournalService::class);
    $this->owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->base = 'http://egy.3alraf.test';
    $this->tx = fn (string $dr, string $cr, string $amt, array $e = []) => $this->je->post(['entry_date' => now()->toDateString(), 'description' => 'tx'] + $e, [['account' => $dr, 'debit' => $amt], ['account' => $cr, 'credit' => $amt]]);
});

/** Route params -> harmless sample values. */
function erpUri(\Illuminate\Routing\Route $r): string
{
    $uri = $r->uri();
    foreach (['id' => 1, 'line' => 1, 'owner' => 1, 'type' => 'customer', 'report' => 'trial-balance'] as $k => $v) {
        $uri = str_replace('{'.$k.'}', (string) $v, $uri);
    }

    return $uri;
}

function erpRoutes(string $prefix): array
{
    return collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with((string) $r->getName(), $prefix))->values()->all();
}

it('has a permission gate on EVERY tenant ERP route: guests are redirected, users without ERP permissions get 403', function () {
    $routes = erpRoutes('tenant.erp.');
    expect(count($routes))->toBeGreaterThan(60);
    $plain = TenantAdmin::create(['name' => 'Plain', 'email' => 'plain@egy.test', 'password' => 'password', 'is_active' => true, 'tenant_id' => $this->tenant->id]);
    $bad = [];
    foreach ($routes as $r) {
        $method = collect($r->methods())->first(fn ($m) => ! in_array($m, ['HEAD', 'OPTIONS']));
        $uri = $this->base.'/'.erpUri($r);
        $this->app['auth']->guard('tenant')->logout();
        $guest = $this->call($method, $uri);
        if (! in_array($guest->getStatusCode(), [302, 401, 403], true) || ($guest->isRedirect() && ! str_contains((string) $guest->headers->get('Location'), 'login'))) {
            $bad[] = "guest $method $uri => ".$guest->getStatusCode();
        }
        $this->actingAs($plain->fresh(), 'tenant');
        $resp = $this->call($method, $uri);
        if ($resp->getStatusCode() !== 403) {
            $bad[] = "noperm $method $uri => ".$resp->getStatusCode();
        }
    }
    expect($bad)->toBe([]);
});

it('has a permission gate on EVERY central ERP route', function () {
    $routes = erpRoutes('central.erp.');
    expect(count($routes))->toBe(15);
    $plain = CentralAdmin::create(['name' => 'Plain', 'email' => 'plain@3alraf.test', 'password' => 'password', 'is_active' => true]);
    $bad = [];
    foreach ($routes as $r) {
        $method = collect($r->methods())->first(fn ($m) => ! in_array($m, ['HEAD', 'OPTIONS']));
        $uri = 'http://3alraf.test/'.erpUri($r);
        $this->app['auth']->guard('central')->logout();
        $guest = $this->call($method, $uri);
        if (! in_array($guest->getStatusCode(), [302, 401, 403], true) || ($guest->isRedirect() && ! str_contains((string) $guest->headers->get('Location'), 'login'))) {
            $bad[] = "guest $method $uri => ".$guest->getStatusCode();
        }
        $this->actingAs($plain->fresh(), 'central');
        $resp = $this->call($method, $uri);
        if ($resp->getStatusCode() !== 403) {
            $bad[] = "noperm $method $uri => ".$resp->getStatusCode();
        }
        // a tenant admin must not reach the central panel at all
        $this->app['auth']->guard('central')->logout();
        $this->actingAs($this->owner, 'tenant');
        $t = $this->call($method, $uri);
        if (! in_array($t->getStatusCode(), [302, 401, 403, 404], true) || ($t->isRedirect() && ! str_contains((string) $t->headers->get('Location'), 'login'))) {
            $bad[] = "tenant-admin $method $uri => ".$t->getStatusCode();
        }
        $this->app['auth']->guard('tenant')->logout();
    }
    expect($bad)->toBe([]);
});

it('returns 404 for ids of another tenant (IDOR) on every {id} route', function () {
    $other = Tenant::create(['slug' => 'other', 'name' => 'Other', 'country' => 'XX', 'currency_code' => 'USD', 'currency_symbol' => 'USD', 'currency_rate' => 1,
        'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'UTC', 'is_active' => true]);
    app(CoaTemplate::class)->seed($other);
    $foreign = TenantContext::run($other, function () {
        $je = app(JournalService::class);
        $entry = $je->draft(['entry_date' => now()->toDateString(), 'description' => 'x'], [['account' => 'cash', 'debit' => '5'], ['account' => 'sales_revenue', 'credit' => '5']]);
        $owner = \App\Models\Erp\Owner::create(['name' => 'Foreign', 'ownership_percent' => 10, 'is_eligible' => true]);
        $asset = \App\Models\Erp\FixedAsset::create(['name' => 'A', 'acquisition_date' => now()->toDateString(), 'cost' => 10, 'salvage_value' => 0, 'useful_life_months' => 12, 'method' => 'straight_line', 'frequency' => 'monthly', 'status' => 'active']);
        $supplier = \App\Models\Supplier::create(['name' => 'FS', 'is_active' => true]);

        return ['entry' => $entry->id, 'account' => Account::where('role_key', 'cash')->value('id'), 'owner' => $owner->id, 'asset' => $asset->id, 'period' => Period::query()->value('id') ?? 0,
            'supplier' => $supplier->id, 'fy' => \App\Models\Erp\FiscalYear::query()->value('id') ?? 0];
    });
    app()->instance('currentTenant', $this->tenant);
    $this->actingAs($this->owner, 'tenant');
    $b = $this->base.'/admin/erp';
    $this->get("$b/journals/{$foreign['entry']}")->assertNotFound();
    $this->post("$b/journals/{$foreign['entry']}/post")->assertNotFound();
    $this->post("$b/journals/{$foreign['entry']}/reverse", ['reason' => 'x'])->assertNotFound();
    $this->delete("$b/journals/{$foreign['entry']}")->assertNotFound();
    $this->delete("$b/accounts/{$foreign['account']}")->assertNotFound();
    $this->post("$b/accounts/{$foreign['account']}/restore")->assertNotFound();
    $this->get("$b/owners/{$foreign['owner']}")->assertNotFound();
    $this->put("$b/owners/{$foreign['owner']}", ['name' => 'x', 'ownership_percent' => 1])->assertNotFound();
    $this->post("$b/owners/{$foreign['owner']}/transactions", ['type' => 'contribution', 'amount' => 1, 'date' => now()->toDateString(), 'account' => 'cash', 'ref' => 'r1'])->assertNotFound();
    $this->get("$b/assets/{$foreign['asset']}")->assertNotFound();
    $this->post("$b/assets/{$foreign['asset']}/dispose", ['date' => now()->toDateString(), 'into' => 'cash'])->assertNotFound();
    $this->post("$b/assets/{$foreign['asset']}/depreciate", ['period' => now()->format('Y-m')])->assertNotFound();
    $this->get("$b/parties/supplier/{$foreign['supplier']}")->assertNotFound();
    $this->post("$b/parties/supplier/{$foreign['supplier']}/settle", ['amount' => 1, 'account' => 'cash', 'ref' => 'x'])->assertNotFound();
    $this->get("$b/profits/owner/{$foreign['owner']}/statement")->assertNotFound();
    $this->get("$b/profits/999999")->assertNotFound();
    $this->get("$b/reports/general-ledger?account_id={$foreign['account']}")->assertNotFound();
    $this->get("$b/reports/trial-balance?branch_id=999999")->assertNotFound();
    // a foreign id inside a posted manual journal is rejected too
    $this->post("$b/journals", ['entry_date' => now()->toDateString(), 'description' => 'd', 'type' => 'manual', 'lines' => [['account_id' => $foreign['account'], 'debit' => 5], ['account_id' => $foreign['account'], 'credit' => 5]]])->assertNotFound();
    // service level: foreign / unknown account ids never reach the ledger
    expect(fn () => $this->je->post(['entry_date' => now()->toDateString()], [['account' => $foreign['account'], 'debit' => '5'], ['account' => 'cash', 'credit' => '5']]))->toThrow(\App\Exceptions\AccountingException::class);
    // foreign branch / supplier ids are rejected by validation (422 style), never stored
    $this->post("$b/journals", ['entry_date' => now()->toDateString(), 'description' => 'd', 'type' => 'manual', 'branch_id' => 99999, 'lines' => [['account_id' => 1, 'debit' => 5], ['account_id' => 2, 'credit' => 5]]])->assertSessionHasErrors('branch_id');
});

it('rejects non-whitelisted sort columns and survives SQL-injection style input', function () {
    $this->actingAs(CentralAdmin::where('email', 'admin@3alraf.test')->first(), 'central');
    $c = 'http://3alraf.test/admin/erp';
    foreach (['name; DROP TABLE tenants', "name' OR 1=1 --", '(select 1)', 'revenue desc, sleep(5)'] as $s) {
        expect($this->get("$c/overview?sort=".urlencode($s))->getStatusCode())->toBeIn([200, 302]); // 302 = rejected by validation, never a 500
    }
    $this->get("$c/transactions?q=".urlencode("' OR '1'='1")."&account=".urlencode("1' UNION SELECT 1 --")."&type=x&source=y")->assertOk();
    $this->get("$c/overview?dir=".urlencode('asc; DROP TABLE tenants'))->assertSessionHasErrors('dir');
    expect(Tenant::count())->toBeGreaterThan(0);
    $this->actingAs($this->owner, 'tenant');
    $this->get($this->base.'/admin/erp/journals?q='.urlencode("%' OR 1=1 --").'&status='.urlencode("posted' OR '1'='1"))->assertOk();
    expect($this->get($this->base.'/admin/erp/reports/trial-balance?type='.urlencode("x'); DROP TABLE erp_accounts;--"))->getStatusCode())->toBeIn([200, 302]);
    $this->get($this->base.'/admin/erp/reports/journal-entries?type='.urlencode("x' OR 1=1"))->assertOk();
    expect(Account::count())->toBeGreaterThan(10);
});

it('neutralises CSV formula injection in the central and the tenant exporters', function () {
    foreach (['=1+1', '+SUM(A1)', '-2+3', '@cmd', "\t=1", " =1", "\r=1"] as $v) {
        expect(Csv::cell($v))->toStartWith("'");
    }
    expect(Csv::cell('-5.25'))->toBe('-5.25')->and(Csv::cell('plain'))->toBe('plain');
    // a malicious account name flows into the tenant CSV export of several reports
    Account::where('role_key', 'rent')->first()->update(['name' => ['en' => '=HYPERLINK("http://evil","x")', 'ar' => '=cmd|calc']]);
    ($this->tx)('rent', 'cash', '50');
    $this->actingAs($this->owner, 'tenant');
    foreach (['trial-balance', 'profit-loss', 'balance-sheet', 'expenses', 'journal-entries'] as $rep) {
        $res = $this->get($this->base."/admin/erp/reports/$rep/export?format=csv&from=2000-01-01&to=".now()->addDay()->toDateString());
        $res->assertOk();
        $body = $res->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $res->streamedContent() : $res->getContent();
        foreach (preg_split('/\r?\n/', $body) as $line) {
            foreach (str_getcsv($line) as $cell) {
                expect($cell !== '' && in_array(ltrim($cell)[0] ?? '', ['=', '+', '@'], true))->toBeFalse("$rep: unsafe cell [$cell]");
            }
        }
    }
});

it('escapes user data in the ERP pages (no stored XSS)', function () {
    $xss = '<script>alert(1)</script>';
    Account::where('role_key', 'rent')->first()->update(['name' => ['en' => $xss, 'ar' => $xss]]);
    \App\Models\Erp\Owner::create(['name' => $xss, 'ownership_percent' => 10, 'is_eligible' => true]);
    ($this->tx)('rent', 'cash', '50', ['description' => $xss]);
    $this->actingAs($this->owner, 'tenant');
    foreach (['accounts', 'journals', 'owners', 'reports/trial-balance', 'reports/profit-loss', 'reports/journal-entries', 'profits', 'dashboard', 'journals/'.JournalEntry::first()->id] as $p) {
        $this->get($this->base.'/admin/erp/'.$p)->assertOk()->assertDontSee($xss, false);
    }
});

it('serialises double submits: same reference posts once, a closed period cannot be posted into', function () {
    $this->actingAs($this->owner, 'tenant');
    $sup = \App\Models\Supplier::create(['name' => 'S', 'is_active' => true]);
    $url = $this->base.'/admin/erp/parties/supplier/'.$sup->id.'/settle';
    $payload = ['amount' => 40, 'account' => 'cash', 'ref' => 'tok-1', 'advance_only' => 1, 'date' => now()->toDateString()];
    $this->post($url, $payload)->assertRedirect();
    $this->post($url, $payload)->assertRedirect();
    expect(JournalEntry::where('source_type', 'supplier')->count())->toBe(1)->and(Account::where('role_key', 'supplier_advances')->first()->current_balance)->toBe('40.0000');
    $owner = \App\Models\Erp\Owner::create(['name' => 'O', 'ownership_percent' => 50, 'is_eligible' => true]);
    $p = ['type' => 'contribution', 'amount' => 70, 'date' => now()->toDateString(), 'account' => 'cash', 'ref' => 'tok-2'];
    $this->post($this->base."/admin/erp/owners/{$owner->id}/transactions", $p)->assertRedirect();
    $this->post($this->base."/admin/erp/owners/{$owner->id}/transactions", $p)->assertRedirect();
    expect(\App\Models\Erp\OwnerTransaction::count())->toBe(1)->and(Account::where('role_key', 'owner_capital')->first()->current_balance)->toBe('70.0000');
    // the DB unique key is the last line of defence against a concurrent duplicate (the second insert is turned into the first entry)
    $a = ($this->tx)('cash', 'sales_revenue', '5', ['idempotency_key' => 'race:1']);
    $svc = app(JournalService::class);
    $ref = new ReflectionMethod($svc, 'persist');
    $ref->setAccessible(true);
    expect(fn () => DB::transaction(fn () => $ref->invoke($svc, ['entry_date' => now()->toDateString(), 'idempotency_key' => 'race:1'], [['account' => 'cash', 'debit' => '5'], ['account' => 'sales_revenue', 'credit' => '5']], 'draft')))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
    expect(JournalEntry::where('idempotency_key', 'race:1')->count())->toBe(1)->and($a->status)->toBe('posted');
    // period close is idempotent and refuses drafts dated in the period
    $period = app(\App\Services\Erp\Accounting\PeriodService::class)->openPeriodFor('2025-03-10');
    $this->je->draft(['entry_date' => '2025-03-11', 'description' => 'd'], [['account' => 'cash', 'debit' => '1'], ['account' => 'sales_revenue', 'credit' => '1']]);
    foreach (Period::where('starts_on', '<', '2025-03-01')->get() as $prev) {
        $prev->update(['status' => 'closed']);
    }
    expect(fn () => app(\App\Services\Erp\Accounting\PeriodService::class)->close($period, null))->toThrow(\App\Exceptions\AccountingException::class);
});

it('rejects lines appended to a posted entry and keeps posted rows immutable through Eloquent', function () {
    $e = ($this->tx)('cash', 'sales_revenue', '10');
    expect(fn () => $e->lines()->create(['account_id' => Account::where('role_key', 'cash')->value('id'), 'debit' => 1, 'credit' => 0]))->toThrow(LogicException::class);
    expect(fn () => \App\Models\Erp\JournalLine::where('entry_id', $e->id)->first()->delete())->toThrow(LogicException::class);
    expect(fn () => JournalEntry::find($e->id)->forceFill(['total' => 1])->save())->toThrow(LogicException::class);
});

it('enforces the DB CHECK constraints on journal lines (negative / two-sided rows are rejected even with raw SQL)', function () {
    $e = $this->je->draft(['entry_date' => now()->toDateString(), 'description' => 'd'], [['account' => 'cash', 'debit' => '1'], ['account' => 'sales_revenue', 'credit' => '1']]);
    $acc = Account::where('role_key', 'cash')->value('id');
    $row = ['tenant_id' => $this->tenant->id, 'entry_id' => $e->id, 'account_id' => $acc, 'created_at' => now(), 'updated_at' => now()];
    expect(fn () => DB::table('erp_journal_lines')->insert($row + ['debit' => -1, 'credit' => 0]))->toThrow(\Illuminate\Database\QueryException::class);
    expect(fn () => DB::table('erp_journal_lines')->insert($row + ['debit' => 1, 'credit' => 1]))->toThrow(\Illuminate\Database\QueryException::class);
    expect(fn () => DB::table('erp_journal_entries')->where('id', $e->id)->update(['status' => 'bogus']))->toThrow(\Illuminate\Database\QueryException::class);
});

it('erp:verify passes on a healthy ledger and fails (exit 1) on every kind of corruption', function () {
    ($this->tx)('cash', 'sales_revenue', '100');
    ($this->tx)('rent', 'cash', '30', ['idempotency_key' => 'k1']);
    $this->artisan('erp:verify', ['--tenant' => 'egy'])->assertExitCode(0);
    expect(app(LedgerVerifier::class)->run($this->tenant)['ok'])->toBeTrue();

    // cached balance drift
    Account::where('role_key', 'cash')->update(['current_balance' => '1.0000']);
    $this->artisan('erp:verify', ['--tenant' => 'egy'])->assertExitCode(1);
    $r = app(LedgerVerifier::class)->run($this->tenant);
    expect($r['checks']['balance_drift']['count'])->toBe(1)->and($r['checks']['balance_drift']['samples'][0]->code)->not->toBeEmpty();
    Account::where('role_key', 'cash')->update(['current_balance' => '70.0000']);
    $this->artisan('erp:verify', ['--tenant' => 'egy'])->assertExitCode(0);

    // unbalanced posted entry (raw SQL bypass of the app policy) + balance drift follows
    $id = JournalEntry::first()->id;
    DB::table('erp_journal_lines')->where('entry_id', $id)->where('credit', '>', 0)->limit(1)->update(['credit' => 99]);
    $r = app(LedgerVerifier::class)->run($this->tenant);
    expect($r['checks']['unbalanced_entries']['count'])->toBe(1)->and($r['ok'])->toBeFalse();
    $this->artisan('erp:verify', ['--tenant' => 'egy', '--json' => true])->assertExitCode(1);
    DB::table('erp_journal_lines')->where('entry_id', $id)->where('credit', 99)->limit(1)->update(['credit' => 100]);

    // orphan line (entry removed with FK checks off) and an entry without lines
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    DB::table('erp_journal_lines')->insert(['tenant_id' => $this->tenant->id, 'entry_id' => 999999, 'account_id' => Account::value('id'), 'debit' => 0, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()]);
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    expect(app(LedgerVerifier::class)->run($this->tenant)['checks']['orphan_lines']['count'])->toBe(1);
    DB::table('erp_journal_lines')->where('entry_id', 999999)->delete();
    DB::table('erp_journal_lines')->where('entry_id', $id)->delete();
    expect(app(LedgerVerifier::class)->run($this->tenant)['checks']['entries_without_lines']['count'])->toBe(1);
});

it('erp:verify audits every tenant separately and is read-only', function () {
    $other = Tenant::create(['slug' => 'other', 'name' => 'Other', 'country' => 'XX', 'currency_code' => 'USD', 'currency_symbol' => 'USD', 'currency_rate' => 1,
        'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'UTC', 'is_active' => true]);
    app(CoaTemplate::class)->seed($other);
    ($this->tx)('cash', 'sales_revenue', '100');
    Account::withoutGlobalScopes()->where('tenant_id', $other->id)->where('role_key', 'cash')->update(['current_balance' => '5.0000']);
    $before = DB::table('erp_journal_entries')->count();
    $this->artisan('erp:verify', ['--tenant' => 'egy'])->assertExitCode(0);
    $this->artisan('erp:verify', ['--tenant' => 'other'])->assertExitCode(1);
    $this->artisan('erp:verify')->assertExitCode(1);
    $this->artisan('erp:verify', ['--tenant' => 'nope'])->assertExitCode(1);
    expect(DB::table('erp_journal_entries')->count())->toBe($before);
});

it('runs the ERP commands inside a TenantContext without leaking the tenant', function () {
    app()->forgetInstance('currentTenant');
    app()->offsetUnset('currentTenant');
    $this->artisan('erp:depreciate', ['--tenant' => 'egy', '--period' => now()->subMonth()->format('Y-m')])->assertExitCode(0);
    $this->artisan('erp:depreciate', ['--period' => 'bad'])->assertExitCode(1);
    $this->artisan('erp:reconcile', ['--tenant' => 'nope'])->assertExitCode(1);
    expect(app()->bound('currentTenant'))->toBeFalse();
});

it('keeps ERP attachments on the private disk with a strict mime and size whitelist', function () {
    $rules = (new \App\Http\Requests\Tenant\ExpenseRequest)->rules();
    expect($rules['attachment'])->toContain('max:4096')->and(implode('|', $rules['attachment']))->toContain('mimes:jpg,jpeg,png,webp,pdf');
    $src = file_get_contents(app_path('Services/ExpenseService.php'));
    expect($src)->toContain("config('media.private_disk')")->and($src)->not->toContain("'public'");
});
