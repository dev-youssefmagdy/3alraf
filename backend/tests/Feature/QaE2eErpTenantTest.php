<?php

/*
 * QA E2E — tenant ERP panel, driven through HTTP as a user would (forms, not services).
 * Tests named "QA-BUG-xxx" encode the CORRECT behaviour and fail until the bug is fixed (docs/qa/QA_TEST_REPORT.md).
 * Run alone: php artisan test tests/Feature/QaE2eErpTenantTest.php
 */

use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\Erp\Account;
use App\Models\Erp\FixedAsset;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\Owner;
use App\Models\Erp\Period;
use App\Models\Erp\ProfitDistribution;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Erp\Reports\LedgerReports;
use App\Services\Stock\StockLedger;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Storage::fake('media');
    Storage::fake('private');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    codUpfront($this->tenant, false);
    app()->instance('currentTenant', $this->tenant);
    $this->a = 'http://egy.3alraf.test/admin';
    $this->e = $this->a.'/erp';
    $this->owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($this->owner, 'tenant');
    $this->branch = Branch::first();
    $this->bal = fn (string $role) => (string) Account::where('role_key', $role)->value('current_balance');
});

/** Raw (untranslated) ERP translation keys visible in an HTML page. */
function qaRawKeys(string $html): array
{
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    preg_match_all('/(?<![\w.\/:-])(?:erp|erp_central|erp_dist|erp_ext|erp_rep|access|admin)\.[a-z0-9_]+(?:\.[a-z0-9_\-]+)*\.?(?![\w(])/', $html, $m);

    return array_values(array_unique(array_filter($m[0], fn ($k) => ! preg_match('/^admin\.(3alraf|test|loc)/', $k))));
}

function qaProduct(string $slug = 'qa-p', string $price = '100', string $cost = '60', int $qty = 50): Product
{
    $p = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج '.$slug, 'en' => 'Prod '.$slug], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
    TenantProduct::create(['product_id' => $p->id, 'price' => $price, 'cost' => $cost, 'is_active' => true]);
    if ($qty > 0) {
        app(StockLedger::class)->record(Branch::first()->id, $p->id, 0, T::PURCHASE_IN, $qty, $cost);
    }

    return $p;
}

/** Place an order through the admin POS endpoint (HTTP). */
function qaPosOrder(Product $p, int $qty = 2, ?Customer $c = null): Order
{
    $c ??= Customer::firstOrCreate(['email' => 'qa-buyer@example.com'], ['name' => 'QA Buyer', 'phone' => '1055501234', 'password' => 'secret-123', 'is_active' => true]);
    test()->postJson(test()->a.'/pos/orders', [
        'items' => [['product_id' => $p->id, 'variant_id' => 0, 'qty' => $qty]], 'customer_id' => $c->id,
        'name' => 'Ali', 'phone' => '1001234567', 'city_id' => City::first()->id, 'address' => 'Street 1',
        'shipping_company_id' => ShippingCompany::first()->id, 'payment_method_id' => PaymentMethod::where('type', 'cod')->first()->id,
    ])->assertOk();

    return Order::latest('id')->first();
}

function qaStatus(Order $o, string ...$steps): Order
{
    foreach ($steps as $s) {
        test()->postJson(test()->a."/orders/{$o->id}/status", ['status' => $s])->assertOk();
    }

    return $o->refresh();
}

/** Builds a realistic tenant via the HTTP UI: orders, return, purchase, adjustment, expense, owners, asset, distribution. */
function qaRichData(): array
{
    $t = test();
    $p = qaProduct('qa-a', '100', '60', 80);
    $o1 = qaStatus(qaPosOrder($p, 2), 'confirmed', 'processing', 'shipped', 'delivered');
    $o2 = qaStatus(qaPosOrder($p, 1), 'confirmed');
    $o3 = qaStatus(qaPosOrder($p, 1), 'cancelled');
    $t->postJson($t->a."/orders/{$o1->id}/returns", ['items' => [['order_item_id' => $o1->items->first()->id, 'quantity' => 1]], 'restock' => 1, 'reason' => 'QA'])->assertOk();
    $ret = \App\Models\OrderReturn::latest('id')->first();
    $t->postJson($t->a."/returns/{$ret->id}/approve", ['restock' => 1])->assertOk();
    $t->postJson($t->a."/returns/{$ret->id}/refund", [])->assertOk();
    $supplier = Supplier::first() ?? Supplier::create(['name' => 'QA Supplier', 'is_active' => true]);
    $t->postJson($t->a.'/purchases', ['supplier_id' => $supplier->id, 'branch_id' => Branch::first()->id, 'ordered_at' => now()->toDateString(), 'receive' => 1,
        'items' => [['item' => $p->id.'-0', 'quantity' => 10, 'unit_cost' => 55]]])->assertOk();
    $t->postJson($t->a.'/stock-adjustments', ['branch_id' => Branch::first()->id, 'type' => 'decrease', 'reason' => 'damaged', 'items' => [['item' => $p->id.'-0', 'quantity' => 2]]])->assertOk();
    $cat = \App\Models\ExpenseCategory::first() ?? \App\Models\ExpenseCategory::create(['name' => ['en' => 'Rent', 'ar' => 'إيجار'], 'is_active' => true]);
    $t->post($t->a.'/expenses', ['expense_category_id' => $cat->id, 'amount' => '150.50', 'expense_date' => now()->toDateString()], ['Accept' => 'application/json'])->assertOk();
    $t->post($t->e.'/owners', ['name' => 'Owner A', 'ownership_percent' => 60, 'is_eligible' => 1])->assertRedirect();
    $t->post($t->e.'/owners', ['name' => 'Owner B', 'ownership_percent' => 40, 'is_eligible' => 1])->assertRedirect();
    $ownA = Owner::where('name', 'Owner A')->first();
    $t->post($t->e."/owners/{$ownA->id}/transactions", ['type' => 'contribution', 'amount' => 20000, 'date' => now()->toDateString(), 'account' => 'cash', 'ref' => 'CAP-1'])->assertRedirect();
    $t->post($t->e.'/assets', ['name' => 'Laptop', 'acquisition_date' => now()->startOfMonth()->toDateString(), 'cost' => 1200, 'salvage_value' => 0, 'useful_life_months' => 12, 'method' => 'straight_line', 'paid_from' => 'cash'])->assertRedirect();
    $customer = Customer::where('email', 'qa-buyer@example.com')->first();
    $t->post($t->e.'/journals', ['entry_date' => now()->subMonth()->toDateString(), 'description' => 'QA manual draft', 'type' => 'manual',
        'lines' => [['account_id' => Account::where('role_key', 'rent')->value('id'), 'debit' => 10], ['account_id' => Account::where('role_key', 'cash')->value('id'), 'credit' => 10]]])->assertRedirect();

    return ['product' => $p, 'orders' => [$o1, $o2, $o3], 'return' => $ret, 'supplier' => $supplier, 'owner' => $ownA, 'customer' => $customer,
        'asset' => FixedAsset::first(), 'entry' => JournalEntry::latest('id')->first()];
}

/** Every GET ERP route with real ids substituted. */
function qaTenantGetUris(array $ids): array
{
    $out = [];
    foreach (Route::getRoutes()->getRoutes() as $r) {
        $n = (string) $r->getName();
        if (! str_starts_with($n, 'tenant.erp.') || ! in_array('GET', $r->methods(), true)) {
            continue;
        }
        $uri = $r->uri();
        if ($n === 'tenant.erp.reports.show' || $n === 'tenant.erp.reports.export') {
            foreach (array_keys(\App\Services\Erp\Reports\ReportCatalog::META) as $rep) {
                $u = str_replace('{report}', $rep, $uri);
                $out[$n.':'.$rep] = $n === 'tenant.erp.reports.export' ? [$u, $u.'?format=pdf'] : [$u];
            }

            continue;
        }
        if (str_contains($uri, '{type}')) {
            foreach (['customer' => $ids['customer'], 'supplier' => $ids['supplier']] as $type => $pid) {
                $out[$n.':'.$type] = [str_replace(['{type}', '{id}'], [$type, $pid], $uri)];
            }

            continue;
        }
        $map = ['tenant.erp.journals.show' => $ids['entry'], 'tenant.erp.owners.show' => $ids['owner'], 'tenant.erp.assets.show' => $ids['asset'],
            'tenant.erp.profits.show' => $ids['dist'], 'tenant.erp.profits.statement' => $ids['owner']];
        $uri = str_replace(['{id}', '{owner}'], [(string) ($map[$n] ?? 0), (string) ($map[$n] ?? 0)], $uri);
        $out[$n] = [$uri];
    }

    return $out;
}

it('SMOKE: every tenant ERP GET page/export renders for the owner with realistic data (en), no raw keys', function () {
    $d = qaRichData();
    qaJournal('cash', 'sales_revenue', '10000')->assertSessionHasNoErrors();
    $this->post($this->e.'/profits', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(), 'acknowledge_open' => 1]);
    $dist = ProfitDistribution::latest('id')->first();
    $ids = ['customer' => $d['customer']->id, 'supplier' => $d['supplier']->id, 'entry' => $d['entry']->id, 'owner' => $d['owner']->id, 'asset' => $d['asset']->id, 'dist' => $dist?->id ?? 0];
    $bad = [];
    foreach (qaTenantGetUris($ids) as $name => $uris) {
        foreach ($uris as $uri) {
            $res = $this->get('http://egy.3alraf.test/'.$uri);
            $code = $res->getStatusCode();
            if ($code !== 200) {
                $bad[] = "$name $uri => $code ".substr((string) ($res->exception?->getMessage() ?? ''), 0, 200);

                continue;
            }
            $ct = (string) $res->headers->get('Content-Type');
            if (str_contains($ct, 'html')) {
                $raw = qaRawKeys($res->getContent());
                if ($raw) {
                    $bad[] = "$name RAW KEYS: ".implode(', ', array_slice($raw, 0, 8));
                }
            } elseif (strlen((string) $res->getContent()) === 0 && ! $res instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse && ! $res->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                $bad[] = "$name $uri empty body ($ct)";
            }
        }
    }
    expect($bad)->toBe([]);
});

/** A brand-new tenant: no chart of accounts, no periods, no branch data, own owner admin. */
function qaEmptyTenant(): array
{
    $t = Tenant::create(['slug' => 'qa-new', 'name' => 'QA New', 'country' => 'EG', 'currency_code' => 'EGP', 'currency_symbol' => 'ج.م', 'currency_rate' => 1,
        'default_locale' => 'en', 'locales' => ['en', 'ar'], 'timezone' => 'Africa/Cairo', 'is_active' => true]);
    $adm = TenantAdmin::withoutGlobalScopes()->create(['name' => 'New Owner', 'email' => 'owner@qa-new.test', 'password' => 'password', 'is_active' => true, 'tenant_id' => $t->id]);
    $adm->assignRole(\Spatie\Permission\Models\Role::where(['name' => 'tenant-owner', 'guard_name' => 'tenant'])->first());
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return [$t, $adm->fresh()];
}

it('SMOKE: every tenant ERP GET page renders on an EMPTY brand-new tenant (no CoA, no periods, no data)', function () {
    [$t, $adm] = qaEmptyTenant();
    app()->instance('currentTenant', $t);
    expect(Account::count())->toBe(0);
    $this->actingAs($adm, 'tenant');
    $bad = [];
    foreach (qaTenantGetUris(['customer' => 0, 'supplier' => 0, 'entry' => 0, 'owner' => 0, 'asset' => 0, 'dist' => 0]) as $name => $uris) {
        foreach ($uris as $uri) {
            app()->instance('currentTenant', $t);
            $res = $this->get('http://qa-new.3alraf.test/'.$uri);
            $code = $res->getStatusCode();
            $idRoute = preg_match('#/0(/|$|\?)#', $uri);
            if (! ($code === 200 || ($idRoute && $code === 404))) {
                $bad[] = "$name $uri => $code ".substr((string) ($res->exception?->getMessage() ?? ''), 0, 200);
            } elseif ($code === 200 && str_contains((string) $res->headers->get('Content-Type'), 'html') && ($raw = qaRawKeys($res->getContent()))) {
                $bad[] = "$name RAW KEYS: ".implode(', ', array_slice($raw, 0, 8));
            }
        }
    }
    expect($bad)->toBe([]);
});

it('SMOKE: every tenant ERP GET page renders in ARABIC (admin locale ar) with dir=rtl and no raw keys', function () {
    $d = qaRichData();
    $this->get($this->a.'/locale/ar')->assertRedirect();
    $ids = ['customer' => $d['customer']->id, 'supplier' => $d['supplier']->id, 'entry' => $d['entry']->id, 'owner' => $d['owner']->id, 'asset' => $d['asset']->id, 'dist' => 0];
    $bad = [];
    foreach (qaTenantGetUris($ids) as $name => $uris) {
        if (str_contains($name, 'export') || $name === 'tenant.erp.profits.show') {
            continue;
        }
        $res = $this->get('http://egy.3alraf.test/'.$uris[0]);
        if ($res->getStatusCode() !== 200) {
            $bad[] = "$name => ".$res->getStatusCode().' '.substr((string) ($res->exception?->getMessage() ?? ''), 0, 200);

            continue;
        }
        $html = $res->getContent();
        if (! str_contains($html, 'dir="rtl"')) {
            $bad[] = "$name no dir=rtl";
        }
        if ($raw = qaRawKeys($html)) {
            $bad[] = "$name RAW KEYS: ".implode(', ', array_slice($raw, 0, 8));
        }
    }
    expect($bad)->toBe([]);
});

/* ---------------------------------------------------------------- helpers for journeys */

function qaAcc(string $role): int
{
    return (int) Account::where('role_key', $role)->value('id');
}

/** Post a balanced manual journal through the UI form. */
function qaJournal(string $dr, string $cr, string $amt, array $extra = []): \Illuminate\Testing\TestResponse
{
    return test()->post(test()->e.'/journals', $extra + ['entry_date' => now()->toDateString(), 'description' => 'QA journal', 'type' => 'manual', 'post_now' => 1,
        'lines' => [['account_id' => qaAcc($dr), 'debit' => $amt], ['account_id' => qaAcc($cr), 'credit' => $amt]]]);
}

function qaErrors(): array
{
    $e = session('errors');

    return $e instanceof \Illuminate\Support\ViewErrorBag ? $e->getBag('default')->toArray() : [];
}

/* ---------------------------------------------------------------- chart of accounts */

it('JOURNEY accounts: create, child, duplicate code, wrong parent type, archive with activity, delete unused, restore', function () {
    $this->get($this->e.'/accounts')->assertOk()->assertSee('name="code"', false)->assertSee('name="_token"', false);
    $grp = Account::where('type', 'expense')->where('is_group', true)->first();
    $base = ['name_ar' => 'حساب', 'description' => 'd'];
    $this->post($this->e.'/accounts', $base + ['code' => 'QA6900', 'name_en' => 'QA Group', 'type' => 'expense', 'parent_id' => $grp->id, 'is_group' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $g = Account::where('code', 'QA6900')->first();
    expect($g)->not->toBeNull()->and($g->is_group)->toBeTrue()->and($g->normal_balance)->toBe('debit');
    $this->post($this->e.'/accounts', $base + ['code' => 'QA6901', 'name_en' => 'QA Child', 'type' => 'expense', 'parent_id' => $g->id])->assertSessionHasNoErrors();
    expect(Account::where('code', 'QA6901')->value('parent_id'))->toBe($g->id);
    $this->post($this->e.'/accounts', $base + ['code' => 'QA6901', 'name_en' => 'Dup', 'type' => 'expense'])->assertSessionHasErrors('code');
    $this->post($this->e.'/accounts', $base + ['code' => 'QA6902', 'name_en' => 'Bad', 'type' => 'revenue', 'parent_id' => $g->id])->assertSessionHasErrors('parent_id');
    expect(Account::where('code', 'QA6902')->exists())->toBeFalse();
    $this->get($this->e.'/accounts')->assertOk()->assertSee('QA6901');
    // group with children cannot be removed
    $this->delete($this->e."/accounts/{$g->id}")->assertSessionHasErrors('erp');
    // child with posted activity is archived (kept), unused account is deleted
    $child = Account::where('code', 'QA6901')->first();
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => 'use', 'type' => 'manual', 'post_now' => 1,
        'lines' => [['account_id' => $child->id, 'debit' => 5], ['account_id' => qaAcc('cash'), 'credit' => 5]]])->assertSessionHasNoErrors();
    $this->delete($this->e."/accounts/{$child->id}")->assertSessionHasNoErrors();
    expect($child->fresh()->is_active)->toBeFalse()->and($child->fresh()->archived_at)->not->toBeNull();
    // archived account no longer offered in the journal form, and posting to it is refused
    $this->get($this->e.'/journals/create')->assertOk()->assertDontSee('value="'.$child->id.'"', false);
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => 'x', 'type' => 'manual', 'post_now' => 1,
        'lines' => [['account_id' => $child->id, 'debit' => 5], ['account_id' => qaAcc('cash'), 'credit' => 5]]])->assertSessionHasErrors('erp');
    $this->post($this->e."/accounts/{$child->id}/restore")->assertSessionHasNoErrors();
    expect($child->fresh()->is_active)->toBeTrue();
    $this->post($this->e.'/accounts', $base + ['code' => 'QA6903', 'name_en' => 'Unused', 'type' => 'expense'])->assertSessionHasNoErrors();
    $u = Account::where('code', 'QA6903')->first();
    $this->delete($this->e."/accounts/{$u->id}")->assertSessionHasNoErrors();
    expect(Account::whereKey($u->id)->exists())->toBeFalse();
    // system account cannot be archived
    $this->delete($this->e.'/accounts/'.qaAcc('cash'))->assertSessionHasErrors('erp');
});

it('QA-BUG-001 creating an account without the optional name_ar field returns a validation-safe redirect, not 500', function () {
    $this->get($this->e.'/accounts');
    $res = $this->post($this->e.'/accounts', ['code' => '6950', 'name_en' => 'No Arabic', 'type' => 'expense']);
    expect($res->getStatusCode())->toBe(302);
    expect(Account::where('code', '6950')->value('name'))->not->toBeNull();
});

it('QA-BUG-002 a postable (non-group) account cannot be used as a parent account', function () {
    $this->get($this->e.'/accounts');
    $this->post($this->e.'/accounts', ['code' => '1199', 'name_en' => 'Child of cash', 'name_ar' => 'x', 'type' => 'asset', 'parent_id' => qaAcc('cash')])->assertSessionHasErrors('parent_id');
    expect(Account::where('code', '1199')->exists())->toBeFalse();
});

it('QA-BUG-003 an unknown parent account / tax account / branch id is a validation error, not a 404 page', function () {
    $this->get($this->e.'/accounts');
    $this->from($this->e.'/accounts')->post($this->e.'/accounts', ['code' => '6960', 'name_en' => 'X', 'name_ar' => 'x', 'type' => 'expense', 'parent_id' => 999999])->assertStatus(302)->assertSessionHasErrors('parent_id');
    $this->from($this->e.'/settings')->put($this->e.'/settings', ['reserve_percent' => 10, 'distribution_min_cash_buffer' => 0, 'tax_rate' => 0, 'tax_account_id' => qaAcc('cash')])->assertStatus(302)->assertSessionHasErrors('tax_account_id');
    $this->from($this->e.'/branches')->post($this->e.'/branches/transfer', ['from_branch' => $this->branch->id, 'to_branch' => 999999, 'amount' => 5, 'ref' => 'x'])->assertStatus(302)->assertSessionHasErrors('to_branch');
});

/* ---------------------------------------------------------------- manual journals */

it('JOURNEY journals: draft -> show -> post -> reverse, unbalanced = validation error, delete draft, double submits', function () {
    $this->get($this->e.'/journals/create')->assertOk()->assertSee('name="lines[0][account_id]"', false)->assertSee('name="_token"', false);
    $draft = ['entry_date' => now()->toDateString(), 'description' => 'QA rent draft', 'type' => 'manual',
        'lines' => [['account_id' => qaAcc('rent'), 'debit' => '120.50', 'memo' => 'm1'], ['account_id' => qaAcc('cash'), 'credit' => '120.50']]];
    $this->post($this->e.'/journals', $draft)->assertRedirect();
    $e = JournalEntry::latest('id')->first();
    expect($e->status)->toBe('draft')->and(($this->bal)('rent'))->toBe('0.0000');
    $this->get($this->e."/journals/{$e->id}")->assertOk()->assertSee('QA rent draft')->assertSee('/journals/'.$e->id.'/post"', false);
    // post twice (double click) -> one posting, balance moved once
    $this->post($this->e."/journals/{$e->id}/post")->assertSessionHasNoErrors();
    $this->post($this->e."/journals/{$e->id}/post")->assertSessionHasNoErrors();
    expect($e->fresh()->status)->toBe('posted')->and((float) ($this->bal)('rent'))->toBe(120.5);
    $this->get($this->e."/journals/{$e->id}")->assertOk()->assertSee('/journals/'.$e->id.'/reverse"', false)->assertDontSee('name="_method" value="DELETE"', false);
    // delete a posted entry is refused
    $this->delete($this->e."/journals/{$e->id}")->assertSessionHasErrors('erp');
    // reverse requires a reason
    $this->post($this->e."/journals/{$e->id}/reverse", [])->assertSessionHasErrors('reason');
    $this->post($this->e."/journals/{$e->id}/reverse", ['reason' => 'typo'])->assertRedirect();
    $this->post($this->e."/journals/{$e->id}/reverse", ['reason' => 'typo again']);
    expect($e->fresh()->status)->toBe('reversed')->and(JournalEntry::where('reverses_id', $e->id)->count())->toBe(1)->and((float) ($this->bal)('rent'))->toBe(0.0);
    // unbalanced -> validation error message, nothing stored
    $n = JournalEntry::count();
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => 'bad', 'type' => 'manual',
        'lines' => [['account_id' => qaAcc('rent'), 'debit' => 10], ['account_id' => qaAcc('cash'), 'credit' => 9]]])->assertStatus(302)->assertSessionHasErrors('erp');
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => 'one line', 'type' => 'manual', 'lines' => [['account_id' => qaAcc('rent'), 'debit' => 10]]])->assertSessionHasErrors('lines');
    $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => 'type', 'type' => 'auto', 'lines' => $draft['lines']])->assertSessionHasErrors('type');
    expect(JournalEntry::count())->toBe($n);
    // delete a draft
    $this->post($this->e.'/journals', $draft);
    $d2 = JournalEntry::latest('id')->first();
    $this->delete($this->e."/journals/{$d2->id}")->assertRedirect(route('tenant.erp.journals.index'));
    expect(JournalEntry::whereKey($d2->id)->exists())->toBeFalse();
    // list filters with odd inputs
    $this->get($this->e.'/journals?status=posted&from=2026-12-31&to=2026-01-01')->assertOk();
    $this->get($this->e.'/journals?from=garbage&branch_id=abc&page=-3')->assertOk();
    $this->get($this->e.'/journals?q=QA+rent')->assertOk()->assertSee($e->number);
});

it('QA-BUG-004 amounts in scientific notation (1e3) are rejected by validation instead of crashing with 500 (bcmath ValueError)', function () {
    $c = Customer::create(['name' => 'Sci', 'email' => 'sci@x.com', 'phone' => '1055500777', 'password' => 'secret-123', 'is_active' => true]);
    $this->get($this->e.'/accounts');
    $o = Owner::create(['name' => 'O', 'ownership_percent' => 10, 'is_eligible' => true]);
    $calls = [
        'journal' => fn () => $this->post($this->e.'/journals', ['entry_date' => now()->toDateString(), 'description' => 'x', 'type' => 'manual', 'lines' => [['account_id' => qaAcc('rent'), 'debit' => '1e3'], ['account_id' => qaAcc('cash'), 'credit' => '1e3']]]),
        'receipt' => fn () => $this->post($this->e."/parties/customer/{$c->id}/settle", ['amount' => '1e2', 'account' => 'cash', 'ref' => 'r1']),
        'note' => fn () => $this->post($this->e."/parties/customer/{$c->id}/note", ['kind' => 'credit_note', 'amount' => '1e1', 'ref' => 'r2']),
        'owner%' => fn () => $this->post($this->e.'/owners', ['name' => 'C', 'ownership_percent' => '1e1']),
        'owner tx' => fn () => $this->post($this->e."/owners/{$o->id}/transactions", ['type' => 'contribution', 'amount' => '1e2', 'date' => now()->toDateString(), 'account' => 'cash', 'ref' => 'x1']),
        'asset' => fn () => $this->post($this->e.'/assets', ['name' => 'L', 'acquisition_date' => now()->toDateString(), 'cost' => '1e3', 'useful_life_months' => 12, 'method' => 'straight_line', 'paid_from' => 'cash']),
    ];
    $bad = [];
    foreach ($calls as $k => $fn) {
        $code = $fn()->getStatusCode();
        if ($code >= 500) {
            $bad[] = "$k => $code";
        }
    }
    expect($bad)->toBe([]);
});

it('QA-BUG-005 saving ERP settings with reserve_percent "1e1" must not brick the Profit distribution page (500 on every later visit)', function () {
    $this->get($this->e.'/settings')->assertOk();
    $this->put($this->e.'/settings', ['reserve_percent' => '1e1', 'distribution_min_cash_buffer' => '1e2', 'tax_rate' => 0]);
    $this->get($this->e.'/profits')->assertOk();
});

it('QA-BUG-006 out-of-range journal amounts are a validation error, not a 500 QueryException', function () {
    $this->get($this->e.'/accounts');
    $res = qaJournal('rent', 'cash', '99999999999999999999');
    expect($res->getStatusCode())->toBe(302);
    $res->assertSessionHasErrors();
});

it('QA-BUG-007 manual journals dated outside the supported fiscal range (1900 / 9999) are refused', function () {
    $this->get($this->e.'/accounts');
    qaJournal('rent', 'cash', '5', ['entry_date' => '1900-01-01'])->assertSessionHasErrors('entry_date');
    qaJournal('rent', 'cash', '5', ['entry_date' => '9999-12-31'])->assertSessionHasErrors('entry_date');
    expect(\App\Models\Erp\FiscalYear::whereYear('starts_on', 1900)->orWhereYear('starts_on', 9999)->exists())->toBeFalse();
});

it('QA-BUG-008 journal list search with an array query parameter (?q[]=x) does not 500', function () {
    $this->get($this->e.'/journals?q[]=x')->assertOk();
});

it('QA-BUG-009 an over-long numeric id in an ERP URL returns 404, not a 500 TypeError', function () {
    $this->get($this->e.'/journals/99999999999999999999')->assertNotFound();
    $this->get($this->e.'/owners/99999999999999999999')->assertNotFound();
});

/* ---------------------------------------------------------------- periods */

it('JOURNEY periods: close / reopen (reason required) / post into a closed period via UI shows an error', function () {
    $this->get($this->e.'/periods')->assertOk();
    $p = Period::whereYear('starts_on', now()->year)->orderBy('starts_on')->first(); // periods close in order: the earliest one
    $in = $p->starts_on->toDateString();
    $this->get($this->e.'/periods')->assertSee(route('tenant.erp.periods.close', $p->id), false);
    $this->post($this->e."/periods/{$p->id}/close")->assertSessionHasNoErrors();
    $this->post($this->e."/periods/{$p->id}/close"); // double click
    expect($p->fresh()->isOpen())->toBeFalse();
    $this->get($this->e.'/periods')->assertOk()->assertSee(route('tenant.erp.periods.reopen', $p->id), false);
    // posting into the closed period through the journal form
    $n = JournalEntry::where('status', 'posted')->count();
    qaJournal('rent', 'cash', '7', ['entry_date' => $in])->assertStatus(302)->assertSessionHasErrors('erp');
    expect(JournalEntry::where('status', 'posted')->count())->toBe($n);
    // a receipt into the closed period is refused too
    $c = Customer::create(['name' => 'P', 'email' => 'p@x.com', 'phone' => '1055500666', 'password' => 'secret-123', 'is_active' => true]);
    $this->post($this->e."/parties/customer/{$c->id}/settle", ['amount' => '10', 'account' => 'cash', 'ref' => 'cp1', 'date' => $in])->assertSessionHasErrors('erp');
    $this->post($this->e."/periods/{$p->id}/reopen", [])->assertSessionHasErrors('reason');
    $this->post($this->e."/periods/{$p->id}/reopen", ['reason' => 'late invoice'])->assertSessionHasNoErrors();
    expect($p->fresh()->isOpen())->toBeTrue();
    qaJournal('rent', 'cash', '7', ['entry_date' => $in])->assertSessionHasNoErrors();
    $this->get($this->e.'/periods?year='.now()->year)->assertOk()->assertSee('late invoice');
    $this->get($this->e.'/periods?year=abc')->assertStatus(302);
});

/* ---------------------------------------------------------------- customers & suppliers */

it('JOURNEY customers: opening balance, partial receipt, advance, apply advance, credit/debit notes, statement and ageing', function () {
    $c = Customer::create(['name' => 'Cust QA', 'email' => 'cq@x.com', 'phone' => '1055500555', 'password' => 'secret-123', 'is_active' => true]);
    $this->get($this->e.'/parties/customer')->assertOk();
    $show = $this->get($this->e."/parties/customer/{$c->id}")->assertOk();
    expect(substr_count($show->getContent(), 'name="_token"'))->toBeGreaterThanOrEqual(4);
    $this->post($this->e."/parties/customer/{$c->id}/opening", ['amount' => '500', 'ref' => 'op1', 'date' => now()->startOfMonth()->toDateString()])->assertSessionHasNoErrors();
    $this->post($this->e."/parties/customer/{$c->id}/opening", ['amount' => '500', 'ref' => 'op1', 'date' => now()->startOfMonth()->toDateString()]); // double submit
    expect((float) ($this->bal)('receivable'))->toBe(500.0);
    $this->post($this->e."/parties/customer/{$c->id}/settle", ['amount' => '200', 'account' => 'cash', 'ref' => 'rc1'])->assertSessionHasNoErrors();
    $this->post($this->e."/parties/customer/{$c->id}/settle", ['amount' => '200', 'account' => 'cash', 'ref' => 'rc1']); // double submit
    expect((float) ($this->bal)('receivable'))->toBe(300.0)->and((float) ($this->bal)('cash'))->toBe(200.0);
    // overpayment: 300 settles AR, 50 becomes an advance
    $this->post($this->e."/parties/customer/{$c->id}/settle", ['amount' => '350', 'account' => 'bank', 'ref' => 'rc2'])->assertSessionHasNoErrors();
    expect((float) ($this->bal)('receivable'))->toBe(0.0)->and((float) ($this->bal)('customer_advances'))->toBe(50.0);
    // a debit note re-opens 80 of receivable, the advance is applied against it
    $this->post($this->e."/parties/customer/{$c->id}/note", ['kind' => 'debit_note', 'amount' => '80', 'ref' => 'dn1', 'description' => 'late fee'])->assertSessionHasNoErrors();
    $this->post($this->e."/parties/customer/{$c->id}/apply-advance", ['ref' => 'aa1'])->assertSessionHasNoErrors();
    expect((float) ($this->bal)('customer_advances'))->toBe(0.0)->and((float) ($this->bal)('receivable'))->toBe(30.0);
    $this->post($this->e."/parties/customer/{$c->id}/note", ['kind' => 'credit_note', 'amount' => '30', 'ref' => 'cn1'])->assertSessionHasNoErrors();
    expect((float) ($this->bal)('receivable'))->toBe(0.0);
    $this->post($this->e."/parties/customer/{$c->id}/settle", ['amount' => '0', 'account' => 'cash', 'ref' => 'z'])->assertSessionHasErrors('amount');
    $this->post($this->e."/parties/customer/{$c->id}/settle", ['amount' => '5', 'account' => 'owner_capital', 'ref' => 'z'])->assertSessionHasErrors('account');
    $st = $this->get($this->e."/parties/customer/{$c->id}?from=".now()->startOfMonth()->toDateString())->assertOk();
    expect($st->getContent())->toContain('Cust QA');
    $this->get($this->e.'/parties/customer/ageing')->assertOk();
    $this->get($this->e.'/parties/customer/ageing?as_of=2000-01-01')->assertOk();
    $this->get($this->e.'/reports/receivables')->assertOk();
    expect(app(LedgerReports::class)->trialBalance()['balanced'])->toBeTrue();
});

it('JOURNEY suppliers: purchase on credit, partial payment, advance payment, apply advance, payables report', function () {
    $s = Supplier::create(['name' => 'Supp QA', 'is_active' => true]);
    $p = qaProduct('qa-s', '100', '40', 0);
    $this->postJson($this->a.'/purchases', ['supplier_id' => $s->id, 'branch_id' => $this->branch->id, 'ordered_at' => now()->toDateString(), 'receive' => 1,
        'items' => [['item' => $p->id.'-0', 'quantity' => 10, 'unit_cost' => 40]]])->assertOk();
    expect((float) ($this->bal)('payable'))->toBe(400.0)->and((float) ($this->bal)('inventory'))->toBe(400.0);
    $this->get($this->e."/parties/supplier/{$s->id}")->assertOk()->assertSee('Supp QA');
    $this->post($this->e."/parties/supplier/{$s->id}/settle", ['amount' => '150', 'account' => 'cash', 'ref' => 'sp1'])->assertSessionHasNoErrors();
    expect((float) ($this->bal)('payable'))->toBe(250.0)->and((float) ($this->bal)('cash'))->toBe(-150.0);
    $this->post($this->e."/parties/supplier/{$s->id}/settle", ['amount' => '100', 'account' => 'bank', 'ref' => 'sp2', 'advance_only' => 1])->assertSessionHasNoErrors();
    expect((float) ($this->bal)('payable'))->toBe(250.0)->and((float) ($this->bal)('supplier_advances'))->toBe(100.0);
    $this->post($this->e."/parties/supplier/{$s->id}/apply-advance", ['ref' => 'sa1'])->assertSessionHasNoErrors();
    $this->post($this->e."/parties/supplier/{$s->id}/apply-advance", ['ref' => 'sa1']); // double submit
    expect((float) ($this->bal)('payable'))->toBe(150.0)->and((float) ($this->bal)('supplier_advances'))->toBe(0.0);
    $this->get($this->e.'/parties/supplier/ageing')->assertOk()->assertSee('Supp QA');
    $this->get($this->e.'/reports/payables')->assertOk()->assertSee('Supp QA');
    $this->get($this->e.'/parties/supplier/999999')->assertNotFound();
});

/* ---------------------------------------------------------------- owners */

it('JOURNEY owners: create (total > 100% refused), contribution, withdrawal, update, statement', function () {
    $this->get($this->e.'/owners')->assertOk()->assertSee('name="ownership_percent"', false);
    $this->post($this->e.'/owners', ['name' => 'Alpha', 'ownership_percent' => 70, 'is_eligible' => 1])->assertSessionHasNoErrors();
    $this->post($this->e.'/owners', ['name' => 'Beta', 'ownership_percent' => 40, 'is_eligible' => 1])->assertSessionHasErrors('erp');
    $this->post($this->e.'/owners', ['name' => 'Beta', 'ownership_percent' => 101])->assertSessionHasErrors('ownership_percent');
    $this->post($this->e.'/owners', ['name' => 'Beta', 'ownership_percent' => 30, 'is_eligible' => 1])->assertSessionHasNoErrors();
    $a = Owner::where('name', 'Alpha')->first();
    $this->put($this->e."/owners/{$a->id}", ['name' => 'Alpha', 'ownership_percent' => 71, 'is_eligible' => 1])->assertSessionHasErrors('erp');
    $this->get($this->e."/owners/{$a->id}")->assertOk()->assertSee('name="ref"', false);
    $this->post($this->e."/owners/{$a->id}/transactions", ['type' => 'contribution', 'amount' => 10000, 'date' => now()->toDateString(), 'account' => 'bank', 'ref' => 'c1'])->assertSessionHasNoErrors();
    $this->post($this->e."/owners/{$a->id}/transactions", ['type' => 'contribution', 'amount' => 10000, 'date' => now()->toDateString(), 'account' => 'bank', 'ref' => 'c1']); // double submit
    expect((float) ($this->bal)('owner_capital'))->toBe(10000.0)->and((float) ($this->bal)('bank'))->toBe(10000.0);
    $this->post($this->e."/owners/{$a->id}/transactions", ['type' => 'withdrawal', 'amount' => 1500, 'date' => now()->toDateString(), 'account' => 'bank', 'ref' => 'w1'])->assertSessionHasNoErrors();
    expect(abs((float) ($this->bal)('owner_drawings')))->toBe(1500.0); // contra-equity, stored with the equity sign
    $st = $this->get($this->e."/owners/{$a->id}")->assertOk();
    expect($st->getContent())->toContain('10,000.00')->toContain('1,500.00');
    $this->get($this->e."/owners/{$a->id}?from=2026-12-31&to=2026-01-01")->assertOk();
    $this->get($this->e.'/reports/owner-equity')->assertOk();
});

/* ---------------------------------------------------------------- profit distribution */

it('JOURNEY profit distribution: create -> approve -> post -> pay -> cancel rules, second approver, overlap, double submits', function () {
    $this->get($this->e.'/accounts');
    $this->post($this->e.'/owners', ['name' => 'Alpha', 'ownership_percent' => 60, 'is_eligible' => 1]);
    $this->post($this->e.'/owners', ['name' => 'Beta', 'ownership_percent' => 40, 'is_eligible' => 1]);
    qaJournal('cash', 'sales_revenue', '10000')->assertSessionHasNoErrors();
    $from = now()->startOfMonth()->toDateString();
    $to = now()->toDateString();
    $this->get($this->e."/profits?from=$from&to=$to")->assertOk()->assertSee('name="acknowledge_open"', false);
    // open period not acknowledged -> refused
    $this->post($this->e.'/profits', ['from' => $from, 'to' => $to])->assertSessionHasErrors('erp');
    $this->post($this->e.'/profits', ['from' => $from, 'to' => now()->addMonth()->toDateString(), 'acknowledge_open' => 1])->assertSessionHasErrors('erp');
    $this->post($this->e.'/profits', ['from' => $from, 'to' => $to, 'acknowledge_open' => 1, 'retain_percent' => 10])->assertSessionHasNoErrors();
    $d = ProfitDistribution::latest('id')->first();
    expect($d->status)->toBe('draft')->and($d->lines()->count())->toBe(2);
    // overlapping / duplicate run refused
    $this->post($this->e.'/profits', ['from' => $from, 'to' => $to, 'acknowledge_open' => 1])->assertSessionHasErrors('erp');
    expect(ProfitDistribution::count())->toBe(1);
    // second approver: creator cannot approve
    $this->put($this->e.'/settings', ['reserve_percent' => 0, 'distribution_min_cash_buffer' => 0, 'tax_rate' => 0, 'distribution_requires_second_approver' => 1])->assertSessionHasNoErrors();
    $this->get($this->e."/profits/{$d->id}")->assertOk()->assertSee(route('tenant.erp.profits.approve', $d->id), false);
    $this->post($this->e."/profits/{$d->id}/approve")->assertSessionHasErrors('erp');
    expect($d->fresh()->status)->toBe('draft');
    $second = TenantAdmin::withoutGlobalScopes()->create(['name' => 'CFO', 'email' => 'cfo@egy.test', 'password' => 'password', 'is_active' => true, 'tenant_id' => $this->tenant->id]);
    $second->assignRole('tenant-owner');
    $this->actingAs($second->fresh(), 'tenant');
    $this->post($this->e."/profits/{$d->id}/approve")->assertSessionHasNoErrors();
    $this->post($this->e."/profits/{$d->id}/approve"); // double click
    expect($d->fresh()->status)->toBe('approved');
    $this->actingAs($this->owner, 'tenant');
    $this->post($this->e."/profits/{$d->id}/post")->assertSessionHasNoErrors();
    $this->post($this->e."/profits/{$d->id}/post"); // double click
    expect($d->fresh()->status)->toBe('posted');
    $payable = (float) ($this->bal)('distributions_payable');
    expect($payable)->toBe((float) $d->fresh()->distributed_amount);
    $line = $d->lines()->orderBy('id')->first();
    $page = $this->get($this->e."/profits/{$d->id}")->assertOk();
    expect($page->getContent())->toContain(route('tenant.erp.profits.pay', [$d->id, $line->id]));
    $this->post($this->e."/profits/{$d->id}/lines/{$line->id}/pay", ['account' => 'cash'])->assertSessionHasNoErrors();
    $this->post($this->e."/profits/{$d->id}/lines/{$line->id}/pay", ['account' => 'cash']); // double submit
    expect(JournalEntry::where('idempotency_key', "profitdist:{$d->id}:line:{$line->id}:pay")->count())->toBe(1)
        ->and((float) ($this->bal)('distributions_payable'))->toBe(round($payable - (float) $line->amount, 2));
    // cannot cancel once a line is paid
    $this->post($this->e."/profits/{$d->id}/cancel", ['reason' => 'oops'])->assertSessionHasErrors('erp');
    expect($d->fresh()->status)->toBe('posted');
    $this->get($this->e.'/profits/report')->assertOk()->assertSee('Alpha');
    $this->get($this->e."/profits/owner/{$line->owner_id}/statement?distribution={$d->id}")->assertOk()->assertSee('Alpha');
});

it('JOURNEY profit distribution: cancelling a posted unpaid run reverses its journals and frees the period', function () {
    $this->get($this->e.'/accounts');
    $this->post($this->e.'/owners', ['name' => 'Solo', 'ownership_percent' => 100, 'is_eligible' => 1]);
    qaJournal('cash', 'sales_revenue', '5000')->assertSessionHasNoErrors();
    $range = ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(), 'acknowledge_open' => 1];
    $this->post($this->e.'/profits', $range)->assertSessionHasNoErrors();
    $d = ProfitDistribution::latest('id')->first();
    $this->post($this->e."/profits/{$d->id}/approve")->assertSessionHasNoErrors();
    $this->post($this->e."/profits/{$d->id}/post")->assertSessionHasNoErrors();
    $this->post($this->e."/profits/{$d->id}/cancel", ['reason' => 'mistake'])->assertSessionHasNoErrors();
    expect($d->fresh()->status)->toBe('cancelled')->and((float) ($this->bal)('distributions_payable'))->toBe(0.0);
    $this->get($this->e."/profits/{$d->id}")->assertOk()->assertDontSee(route('tenant.erp.profits.cancel', $d->id), false);
    $this->post($this->e."/profits/{$d->id}/approve")->assertSessionHasErrors('erp');
    // a new run can be created for the same period
    $this->post($this->e.'/profits', $range)->assertSessionHasNoErrors();
    expect(ProfitDistribution::where('status', 'draft')->count())->toBe(1);
    expect(app(LedgerReports::class)->trialBalance()['balanced'])->toBeTrue();
});

/* ---------------------------------------------------------------- fixed assets */

it('JOURNEY fixed asset: register -> depreciate (artisan + UI, idempotent) -> transfer -> dispose', function () {
    $this->get($this->e.'/assets')->assertOk();
    $b2 = Branch::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => ['ar' => 'فرع 2', 'en' => 'Branch Two'], 'is_active' => true]);
    $start = now()->subMonths(3)->startOfMonth();
    $this->post($this->e.'/assets', ['name' => 'Van', 'category' => 'vehicles', 'acquisition_date' => $start->toDateString(), 'cost' => 12000, 'salvage_value' => 0,
        'useful_life_months' => 12, 'method' => 'straight_line', 'paid_from' => 'cash', 'branch_id' => $this->branch->id])->assertSessionHasNoErrors();
    $a = FixedAsset::latest('id')->first();
    expect((float) ($this->bal)('fixed_assets'))->toBe(12000.0);
    $this->get($this->e."/assets/{$a->id}")->assertOk()->assertSee('Van');
    $period = $start->copy()->addMonth()->format('Y-m');
    $this->artisan('erp:depreciate', ['--tenant' => 'egy', '--period' => $period])->assertExitCode(0);
    app()->instance('currentTenant', $this->tenant);
    $acc = abs((float) ($this->bal)('accumulated_depreciation')); // catch-up: acquisition month + next month
    expect($acc)->toBe(2000.0);
    $this->artisan('erp:depreciate', ['--tenant' => 'egy', '--period' => $period])->assertExitCode(0); // re-run: idempotent
    app()->instance('currentTenant', $this->tenant);
    $this->post($this->e."/assets/{$a->id}/depreciate", ['period' => $period])->assertSessionHasNoErrors();
    $this->post($this->e."/assets/{$a->id}/depreciate", ['period' => $period]); // double submit
    expect(abs((float) ($this->bal)('accumulated_depreciation')))->toBe($acc);
    $this->post($this->e."/assets/{$a->id}/depreciate", ['period' => '2026-13'])->assertSessionHasErrors('period');
    $this->post($this->e."/assets/{$a->id}/transfer", ['branch_id' => $b2->id])->assertSessionHasNoErrors();
    expect($a->fresh()->branch_id)->toBe($b2->id);
    $this->post($this->e."/assets/{$a->id}/dispose", ['date' => now()->toDateString(), 'proceeds' => 9000, 'into' => 'cash'])->assertSessionHasNoErrors();
    expect($a->fresh()->status)->not->toBe('active')->and((float) ($this->bal)('fixed_assets'))->toBe(0.0);
    $this->post($this->e."/assets/{$a->id}/dispose", ['date' => now()->toDateString(), 'proceeds' => 9000, 'into' => 'cash']); // double submit
    expect((float) ($this->bal)('fixed_assets'))->toBe(0.0);
    $this->get($this->e."/assets/{$a->id}")->assertOk();
    $this->get($this->e.'/assets')->assertOk()->assertSee('Van');
    expect(app(LedgerReports::class)->trialBalance()['balanced'])->toBeTrue();
});

/* ---------------------------------------------------------------- branches, settings, migration, dashboard */

it('JOURNEY branch cash transfer: success, same branch refused, double submit with the form ref posts once', function () {
    $this->get($this->e.'/accounts');
    $b2 = Branch::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => ['ar' => 'فرع 2', 'en' => 'Branch Two'], 'is_active' => true]);
    $page = $this->get($this->e.'/branches')->assertOk()->assertSee('name="ref"', false);
    $payload = ['from_branch' => $this->branch->id, 'to_branch' => $b2->id, 'amount' => '250.00', 'role' => 'cash', 'ref' => 'bt-1'];
    $this->post($this->e.'/branches/transfer', $payload)->assertRedirect(route('tenant.erp.branches.index'));
    $this->post($this->e.'/branches/transfer', $payload);
    expect(JournalEntry::where('source_type', 'branch_cash_transfer')->count())->toBe(1)->and((float) ($this->bal)('cash'))->toBe(0.0);
    $this->post($this->e.'/branches/transfer', ['to_branch' => $this->branch->id] + $payload)->assertSessionHasErrors('erp');
    $this->get($this->e.'/branches?from=2026-12-31&to=2026-01-01')->assertOk();
    $this->get($this->e.'/reports/branch-profitability')->assertOk();
});

it('JOURNEY settings: save, validation, exchange rate add/delete', function () {
    $this->get($this->e.'/settings')->assertOk()->assertSee('name="reserve_percent"', false);
    $this->put($this->e.'/settings', ['reserve_percent' => 12.5, 'distribution_min_cash_buffer' => 1000, 'tax_rate' => 22.5, 'tax_account_id' => Account::where('type', 'expense')->where('is_group', false)->value('id'), 'revenue_policy' => str_repeat('R', 1000)])->assertSessionHasNoErrors();
    $s = app(\App\Services\Erp\Equity\ErpSettings::class);
    expect((float) $s->get('reserve_percent'))->toBe(12.5)->and((float) $s->get('tax_rate'))->toBe(22.5);
    $this->get($this->e.'/settings')->assertOk()->assertSee('12.5');
    $this->put($this->e.'/settings', ['reserve_percent' => 101, 'distribution_min_cash_buffer' => -1, 'tax_rate' => 'x'])->assertSessionHasErrors(['reserve_percent', 'distribution_min_cash_buffer', 'tax_rate']);
    $this->put($this->e.'/settings', ['reserve_percent' => 1, 'distribution_min_cash_buffer' => 0, 'tax_rate' => 0, 'revenue_policy' => str_repeat('R', 1001)])->assertSessionHasErrors('revenue_policy');
    $this->post($this->e.'/settings/rates', ['from_currency' => 'egp', 'to_currency' => 'usd', 'rate' => '0.0205', 'effective_date' => now()->toDateString()])->assertSessionHasNoErrors();
    $this->post($this->e.'/settings/rates', ['from_currency' => 'egp', 'to_currency' => 'egp', 'rate' => '1', 'effective_date' => now()->toDateString()])->assertSessionHasErrors('to_currency');
    $r = \App\Models\Erp\ExchangeRate::where('tenant_id', $this->tenant->id)->first();
    expect($r->from_currency)->toBe('EGP');
    $this->get($this->e.'/settings')->assertSee('1 EGP = 0.0205 USD');
    $this->delete($this->e."/settings/rates/{$r->id}")->assertSessionHasNoErrors();
    expect(\App\Models\Erp\ExchangeRate::count())->toBe(0);
});

it('JOURNEY migration page: detect, post missing, toggle auto-posting, reconcile', function () {
    $p = qaProduct('qa-m');
    // auto-posting off -> business documents produce no journals -> "detect" finds them -> "post missing" repairs
    $this->get($this->e.'/migration')->assertOk();
    $this->put($this->e.'/migration/auto-posting', ['enabled' => 0])->assertSessionHasNoErrors();
    expect(app(\App\Services\Erp\Equity\ErpSettings::class)->bool('auto_posting_enabled'))->toBeFalse();
    $o = qaStatus(qaPosOrder($p, 1), 'confirmed', 'processing', 'shipped'); // pass 1 (ERP-011): revenue is recognised at shipment by default
    expect(JournalEntry::where('source_type', 'order')->where('source_id', $o->id)->count())->toBe(0);
    $this->post($this->e.'/migration/detect')->assertOk();
    $this->post($this->e.'/migration/post-missing')->assertRedirect(route('tenant.erp.migration.index'));
    $this->post($this->e.'/migration/post-missing'); // double submit -> nothing duplicated
    expect(JournalEntry::where('source_type', 'order')->where('source_id', $o->id)->where('status', 'posted')->count())->toBe(1);
    $this->put($this->e.'/migration/auto-posting', ['enabled' => 1]);
    expect(app(\App\Services\Erp\Equity\ErpSettings::class)->bool('auto_posting_enabled'))->toBeTrue();
    $this->post($this->e.'/migration/reconcile')->assertOk();
    expect(app(LedgerReports::class)->trialBalance()['balanced'])->toBeTrue();
});

/* ---------------------------------------------------------------- business flows from the existing admin UI */

it('E2E business flows (orders, returns, purchases, stock, expenses, POS) are reflected by ERP pages; TB balanced; dashboard KPIs = P&L', function () {
    $p = qaProduct('qa-flow', '100', '60', 0);
    $s = Supplier::create(['name' => 'Flow Supplier', 'is_active' => true]);
    $b2 = Branch::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => ['ar' => 'فرع 2', 'en' => 'Branch Two'], 'is_active' => true]);
    // purchase create (draft) then receive
    $this->postJson($this->a.'/purchases', ['supplier_id' => $s->id, 'branch_id' => $this->branch->id, 'ordered_at' => now()->toDateString(), 'items' => [['item' => $p->id.'-0', 'quantity' => 30, 'unit_cost' => 60]]])->assertOk();
    $po = \App\Models\PurchaseOrder::latest('id')->first();
    $this->postJson($this->a."/purchases/{$po->id}/receive")->assertOk();
    $this->postJson($this->a."/purchases/{$po->id}/receive")->assertStatus(422);
    // orders through the POS: deliver, confirm-only, cancel after confirm
    $o1 = qaStatus(qaPosOrder($p, 3), 'confirmed', 'processing', 'shipped', 'delivered');
    $o2 = qaStatus(qaPosOrder($p, 1), 'confirmed', 'processing', 'shipped'); // ERP-011: posted at shipment (default policy)
    $o3 = qaStatus(qaPosOrder($p, 1), 'confirmed', 'cancelled');
    // return + refund
    $this->postJson($this->a."/orders/{$o1->id}/returns", ['items' => [['order_item_id' => $o1->items->first()->id, 'quantity' => 1]], 'restock' => 1])->assertOk();
    $ret = \App\Models\OrderReturn::latest('id')->first();
    $this->postJson($this->a."/returns/{$ret->id}/approve", ['restock' => 1])->assertOk();
    $this->postJson($this->a."/returns/{$ret->id}/refund")->assertOk();
    // stock adjustment + transfer
    $this->postJson($this->a.'/stock-adjustments', ['branch_id' => $this->branch->id, 'type' => 'decrease', 'reason' => 'damaged', 'items' => [['item' => $p->id.'-0', 'quantity' => 2]]])->assertOk();
    $this->postJson($this->a.'/stock-transfers', ['from_branch_id' => $this->branch->id, 'to_branch_id' => $b2->id, 'items' => [['item' => $p->id.'-0', 'quantity' => 4]]])->assertOk();
    $tr = \App\Models\StockTransfer::latest('id')->first();
    $this->postJson($this->a."/stock-transfers/{$tr->id}/send")->assertOk();
    $this->postJson($this->a."/stock-transfers/{$tr->id}/receive")->assertOk();
    // expenses CRUD
    $cat = \App\Models\ExpenseCategory::first() ?? \App\Models\ExpenseCategory::create(['name' => ['en' => 'Rent', 'ar' => 'إيجار'], 'is_active' => true]);
    $this->post($this->a.'/expenses', ['expense_category_id' => $cat->id, 'amount' => '300', 'expense_date' => now()->toDateString()], ['Accept' => 'application/json'])->assertOk();
    $ex = \App\Models\Expense::latest('id')->first();
    $this->post($this->a."/expenses/{$ex->id}", ['_method' => 'PUT', 'expense_category_id' => $cat->id, 'amount' => '250', 'expense_date' => now()->toDateString()], ['Accept' => 'application/json'])->assertOk();
    $this->post($this->a.'/expenses', ['expense_category_id' => $cat->id, 'amount' => '40', 'expense_date' => now()->toDateString()], ['Accept' => 'application/json'])->assertOk();
    $this->deleteJson($this->a.'/expenses/'.\App\Models\Expense::latest('id')->value('id'))->assertOk();

    // ledger effects
    expect(JournalEntry::where('source_type', 'order')->where('source_id', $o1->id)->where('status', 'posted')->exists())->toBeTrue()
        ->and(JournalEntry::where('source_type', 'order')->where('source_id', $o2->id)->where('status', 'posted')->exists())->toBeTrue();
    expect(JournalEntry::where('description', 'like', '%')->where('status', 'posted')->count())->toBeGreaterThan(8);
    $tb = app(LedgerReports::class)->trialBalance();
    expect($tb['balanced'])->toBeTrue();
    $this->get($this->e.'/reports/trial-balance')->assertOk();
    $this->get($this->e.'/journals')->assertOk()->assertSee(JournalEntry::latest('id')->value('number'));
    // expense net effect = 250 (edit re-posts, delete reverses)
    $f = \App\Services\Erp\Reports\ErpReportFilter::make(now()->startOfMonth()->toDateString(), now()->toDateString());
    $pl = app(LedgerReports::class)->profitLoss($f->from, $f->to);
    expect((float) $pl['totals']['opex'])->toBeGreaterThanOrEqual(250.0);
    // dashboard KPIs equal the P&L statement
    $dash = $this->get($this->e.'/dashboard')->assertOk()->getContent();
    preg_match('/data-kpi="net_profit">([^<]+)</', $dash, $m);
    expect(trim($m[1] ?? ''))->toBe(number_format((float) $pl['net_profit'], 2));
    preg_match('/data-kpi="net_revenue">([^<]+)</', $dash, $m);
    expect(trim($m[1] ?? ''))->toBe(number_format((float) $pl['totals']['revenue'], 2));
    $plPage = $this->get($this->e.'/reports/profit-loss')->assertOk()->getContent();
    expect($plPage)->toContain(number_format((float) $pl['net_profit'], 2));
    // dashboard with filters
    $this->get($this->e.'/dashboard?from=2026-12-31&to=2026-01-01')->assertOk();
    $this->get($this->e.'/dashboard?branch_id='.$this->branch->id)->assertOk();
    $this->get($this->e.'/dashboard?branch_id=999999')->assertNotFound();
    $this->get($this->e.'/reports/inventory-valuation')->assertOk();
    $this->get($this->e.'/reports/sales')->assertOk()->assertSee($o1->number ?? (string) $o1->id);
});

/* ---------------------------------------------------------------- exports */

it('EXPORTS: every report exports CSV (text/csv, non-empty, BOM/UTF-8) and PDF (application/pdf) in en and ar', function () {
    qaRichData();
    $bad = [];
    foreach (['en', 'ar'] as $loc) {
        $this->get($this->a.'/locale/'.$loc);
        foreach (array_keys(\App\Services\Erp\Reports\ReportCatalog::META) as $rep) {
            $q = $rep === 'general-ledger' ? '&account_id='.qaAcc('cash') : '';
            $csv = $this->get($this->e."/reports/$rep/export?x=1$q");
            if ($csv->getStatusCode() !== 200 || ! str_contains((string) $csv->headers->get('Content-Type'), 'text/csv') || strlen($csv->getContent()) < 5 || ! str_contains((string) $csv->headers->get('Content-Disposition'), 'attachment')) {
                $bad[] = "$loc csv $rep ".$csv->getStatusCode().' '.$csv->headers->get('Content-Type');
            }
            $pdf = $this->get($this->e."/reports/$rep/export?format=pdf$q");
            $body = $pdf->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $pdf->streamedContent() : $pdf->getContent();
            if ($pdf->getStatusCode() !== 200 || ! str_contains((string) $pdf->headers->get('Content-Type'), 'pdf') || ! str_starts_with((string) $body, '%PDF')) {
                $bad[] = "$loc pdf $rep ".$pdf->getStatusCode().' '.$pdf->headers->get('Content-Type');
            }
        }
    }
    expect($bad)->toBe([]);
});

it('QA-BUG-015 a fiscal year / period that has not ended yet cannot be closed (no premature year-end close of the running year or future months)', function () {
    $this->get($this->e.'/periods?year='.(now()->year - 1));
    $prev = \App\Models\Erp\FiscalYear::whereYear('starts_on', now()->year - 1)->first();
    $this->post($this->e."/periods/years/{$prev->id}/close")->assertSessionHasNoErrors(); // a finished year closes fine
    $this->get($this->e.'/periods');
    $cur = \App\Models\Erp\FiscalYear::whereYear('starts_on', now()->year)->first();
    $this->post($this->e."/periods/years/{$cur->id}/close")->assertSessionHasErrors('erp');
    expect($cur->fresh()->status)->toBe('open');
    // and today's operations can still be posted
    qaJournal('rent', 'cash', '5')->assertSessionHasNoErrors();
    // a period that has not started yet cannot be closed either
    $this->get($this->e.'/periods?year='.(now()->year + 1));
    $future = Period::whereYear('starts_on', now()->year + 1)->orderBy('starts_on')->first();
    $this->post($this->e."/periods/{$future->id}/close");
    expect($future->fresh()->isOpen())->toBeTrue();
});
