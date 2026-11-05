<?php

use App\Exceptions\AccountingException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Erp\Account;
use App\Models\Erp\FixedAsset;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\Owner;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Assets\AssetService;
use App\Services\Erp\Owners\OwnerService;
use App\Services\Erp\Parties\PartyService;
use App\Services\Erp\Reports\PartyLedger;
use Database\Seeders\FoundationSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    app(CoaTemplate::class)->seed($this->tenant);
    $this->je = app(JournalService::class);
    $this->parties = app(PartyService::class);
    $this->ledger = app(PartyLedger::class);
    $this->owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($this->owner, 'tenant');
    $this->sup = Supplier::create(['name' => 'Sup', 'is_active' => true]);
    $this->cust = Customer::create(['name' => 'Cus', 'email' => 'c@x.test', 'phone' => '01000000555', 'password' => 'secret123']);
    $this->bal = fn (string $role) => (string) Account::where('role_key', $role)->value('current_balance');
    // a supplier bill: Dr inventory / Cr payable (party supplier)
    $this->bill = fn (string $amt, ?string $date = null, ?int $sup = null) => $this->je->post(['entry_date' => $date ?? now()->toDateString(), 'description' => 'bill'], [
        ['account' => 'inventory', 'debit' => $amt], ['account' => 'payable', 'credit' => $amt, 'party_type' => 'supplier', 'party_id' => $sup ?? $this->sup->id]]);
    $this->invoice = fn (string $amt, ?string $date = null) => $this->je->post(['entry_date' => $date ?? now()->toDateString(), 'description' => 'inv'], [
        ['account' => 'receivable', 'debit' => $amt, 'party_type' => 'customer', 'party_id' => $this->cust->id], ['account' => 'sales_revenue', 'credit' => $amt]]);
    $this->asOwner = function () {
        $this->actingAs($this->owner->fresh(), 'tenant');

        return 'http://egy.3alraf.test/admin/erp';
    };
});

it('reconciles party balances to the receivable and payable control accounts', function () {
    ($this->bill)('1000');
    ($this->invoice)('300');
    $this->je->post(['entry_date' => now()->toDateString()], [['account' => 'receivable', 'debit' => '50'], ['account' => 'sales_revenue', 'credit' => '50']]); // no party (guest)
    $s = $this->ledger->reconcile('supplier');
    $c = $this->ledger->reconcile('customer');
    expect($s['ok'])->toBeTrue()->and($s['parties'])->toBe('1000.0000')->and($s['control'])->toBe('1000.0000');
    expect($c['ok'])->toBeTrue()->and($c['parties'])->toBe('300.0000')->and($c['unattributed'])->toBe('50.0000')->and($c['control'])->toBe('350.0000');
    expect($this->ledger->balances('supplier')[0]['name'])->toBe('Sup');
    $this->parties->receive($this->cust->id, '500', 'r1'); // 300 settles, 200 advance
    expect($this->ledger->reconcile('customer')['ok'])->toBeTrue()->and($this->ledger->position('customer', $this->cust->id)['advance'])->toBe('200.0000');
});

it('supports partial supplier payments, then advances for the excess and applies them to later bills', function () {
    ($this->bill)('1000');
    $this->parties->pay($this->sup->id, '400', 'p1', 'bank');
    expect($this->ledger->position('supplier', $this->sup->id)['outstanding'])->toBe('600.0000')->and(($this->bal)('bank'))->toBe('-400.0000');
    $this->parties->pay($this->sup->id, '800', 'p2'); // 600 settles + 200 advance
    $p = $this->ledger->position('supplier', $this->sup->id);
    expect($p['outstanding'])->toBe('0.0000')->and($p['advance'])->toBe('200.0000')->and(($this->bal)('supplier_advances'))->toBe('200.0000')->and(($this->bal)('payable'))->toBe('0.0000');
    expect($this->parties->pay($this->sup->id, '800', 'p2')->id)->toBe(JournalEntry::where('idempotency_key', 'party:supplier:'.$this->sup->id.':pay:p2')->value('id')); // idempotent
    expect(($this->bal)('cash'))->toBe('-800.0000');
    ($this->bill)('500');
    $this->parties->applyAdvance('supplier', $this->sup->id, 'a1');
    $p = $this->ledger->position('supplier', $this->sup->id);
    expect($p['outstanding'])->toBe('300.0000')->and($p['advance'])->toBe('0.0000')->and($p['balance'])->toBe('300.0000');
    // advance-only payment books the whole amount as advance even though a balance is open
    $this->parties->pay($this->sup->id, '100', 'p3', 'cash', null, true);
    expect($this->ledger->position('supplier', $this->sup->id)['advance'])->toBe('100.0000')->and($this->ledger->reconcile('supplier')['ok'])->toBeTrue();
});

it('ages open items oldest first into the 0-30/31-60/61-90/90+ buckets', function () {
    ($this->bill)('1000', now()->subDays(100)->toDateString());
    ($this->bill)('500', now()->subDays(70)->toDateString());
    ($this->bill)('300', now()->subDays(40)->toDateString());
    ($this->bill)('200', now()->subDays(10)->toDateString());
    $this->parties->pay($this->sup->id, '1200', 'ag', 'cash', now()->subDays(5)->toDateString());
    $r = $this->ledger->ageing('supplier')['rows'][0];
    expect($r['b90'])->toBe('0')->and($r['b61'])->toBe('300.0000')->and($r['b31'])->toBe('300.0000')->and($r['b0'])->toBe('200.0000')->and($r['total'])->toBe('800.0000');
    expect($this->ledger->ageing('supplier')['totals']['total'])->toBe('800.0000');
});

it('posts credit notes, debit notes and opening balances with the party', function () {
    $this->parties->opening('customer', $this->cust->id, '400', 'o1', now()->subDays(60)->toDateString());
    expect(JournalEntry::where('type', 'opening')->count())->toBe(1)->and(($this->bal)('opening_equity'))->toBe('400.0000');
    $this->parties->note('customer', $this->cust->id, 'credit_note', '100', 'n1');
    $this->parties->note('customer', $this->cust->id, 'debit_note', '30', 'n2');
    expect($this->ledger->position('customer', $this->cust->id)['balance'])->toBe('330.0000')->and(JournalEntry::whereIn('type', ['credit_note', 'debit_note'])->count())->toBe(2);
    $this->parties->opening('supplier', $this->sup->id, '250', 'o2');
    $this->parties->note('supplier', $this->sup->id, 'debit_note', '50', 'n3');
    expect($this->ledger->position('supplier', $this->sup->id)['balance'])->toBe('200.0000')->and($this->ledger->reconcile('supplier')['ok'])->toBeTrue();
    $st = $this->ledger->statement('customer', $this->cust->id, now()->subDays(10)->toDateString(), now()->toDateString());
    expect($st['opening'])->toBe('400.0000')->and($st['closing'])->toBe('330.0000')->and(count($st['rows']))->toBe(2);
});

it('records owner contributions and withdrawals and derives equity from the ledger', function () {
    $svc = app(OwnerService::class);
    $o = $svc->save(['name' => 'Ali', 'ownership_percent' => 60, 'is_eligible' => true]);
    $svc->transact($o, 'contribution', '1000', now()->toDateString(), 'bank', null, null, 'c1');
    expect(fn () => $svc->transact($o, 'withdrawal', '200', now()->toDateString(), 'cash', null, null, 'w0'))->toThrow(AccountingException::class); // no cash on hand (ERP-031)
    $svc->transact($o, 'withdrawal', '200', now()->toDateString(), 'bank', null, null, 'w1');
    $svc->transact($o, 'withdrawal', '200', now()->toDateString(), 'bank', null, null, 'w1'); // replay
    expect(($this->bal)('owner_capital'))->toBe('1000.0000')->and(($this->bal)('owner_drawings'))->toBe('-200.0000')->and(($this->bal)('bank'))->toBe('800.0000')->and(($this->bal)('cash'))->toBe('0.0000');
    expect($svc->equity()[$o->id]['equity'])->toBe('800.0000')->and($o->transactions()->count())->toBe(2);
    $st = $svc->statement($o, now()->startOfYear()->toDateString(), now()->toDateString());
    expect($st['closing'])->toBe('800.0000')->and(count($st['rows']))->toBe(2);
    $b = Branch::first();
    $svc->allocateToBranch($b->id, '300', now()->toDateString(), null, null, 'bank'); // zero-sum: bank moves from head office to the branch (ERP-006)
    expect(($this->bal)('branch_capital'))->toBe('300.0000')->and(($this->bal)('bank'))->toBe('800.0000')->and(($this->bal)('owner_capital'))->toBe('700.0000')
        ->and(\App\Models\Erp\JournalLine::where('branch_id', $b->id)->count())->toBe(2);
});

it('rejects ownership above 100 percent', function () {
    $svc = app(OwnerService::class);
    $a = $svc->save(['name' => 'A', 'ownership_percent' => 60]);
    expect(fn () => $svc->save(['name' => 'B', 'ownership_percent' => 50]))->toThrow(AccountingException::class);
    $b = $svc->save(['name' => 'B', 'ownership_percent' => 40]);
    expect(fn () => $svc->save(['name' => 'A', 'ownership_percent' => 61], $a))->toThrow(AccountingException::class);
    $svc->save(['name' => 'A', 'ownership_percent' => 60], $a); // unchanged value is fine
    $base = ($this->asOwner)();
    $this->post("$base/owners", ['name' => 'C', 'ownership_percent' => 5])->assertSessionHasErrors('erp');
    expect(Owner::count())->toBe(2);
});

it('depreciates straight-line down to salvage, idempotently', function () {
    $svc = app(AssetService::class);
    $a = $svc->register(['name' => 'Van', 'category' => 'Vehicles', 'acquisition_date' => '2026-01-10', 'cost' => '1200', 'salvage_value' => '200', 'useful_life_months' => 10]);
    expect(($this->bal)('fixed_assets'))->toBe('1200.0000')->and(($this->bal)('cash'))->toBe('-1200.0000');
    expect($svc->depreciate($a, '2026-03'))->toBe(3)->and($a->accumulated_depreciation)->toBe('300.0000');
    expect($svc->depreciate($a, '2026-03'))->toBe(0); // rerun
    expect($svc->depreciate($a, '2026-12'))->toBe(7);  // catches up, stops at salvage
    expect($a->accumulated_depreciation)->toBe('1000.0000')->and($a->book_value)->toBe('200.0000')->and(($this->bal)('accumulated_depreciation'))->toBe('-1000.0000')->and(($this->bal)('depreciation_expense'))->toBe('1000.0000');
    expect($svc->depreciate($a, '2027-06'))->toBe(0)->and(JournalEntry::where('idempotency_key', 'like', 'asset:'.$a->id.':dep:%')->count())->toBe(10);
    // the artisan command reuses the same idempotency
    $this->artisan('erp:depreciate', ['--tenant' => 'egy', '--period' => '2026-12'])->assertSuccessful();
    expect(JournalEntry::where('idempotency_key', 'like', 'asset:'.$a->id.':dep:%')->count())->toBe(10);
    expect($svc->report()['reconciled'])->toBeTrue();
});

it('books gain or loss on disposal and moves assets between branches', function () {
    $svc = app(AssetService::class);
    $mk = fn () => $svc->register(['name' => 'Press', 'acquisition_date' => '2026-01-05', 'cost' => '1200', 'useful_life_months' => 12, 'branch_id' => Branch::first()->id], 'bank');
    $a = $mk();
    $a = $svc->dispose($a, '2026-06-20', '800'); // 6 months depreciated => NBV 600, gain 200
    expect($a->status)->toBe('disposed')->and($a->disposal_gain_loss)->toBe('200.0000')->and(($this->bal)('other_income'))->toBe('200.0000')->and(($this->bal)('fixed_assets'))->toBe('0.0000')->and(($this->bal)('accumulated_depreciation'))->toBe('0.0000');
    $b = $mk();
    $b = $svc->dispose($b, '2026-06-20', '300'); // loss 300
    expect($b->disposal_gain_loss)->toBe('-300.0000')->and(($this->bal)('other_expense'))->toBe('300.0000');
    expect(fn () => $svc->dispose($b, '2026-07-01'))->toThrow(AccountingException::class);
    $c = $svc->register(['name' => 'Lathe', 'acquisition_date' => '2026-01-05', 'cost' => '600', 'useful_life_months' => 12, 'branch_id' => Branch::first()->id]);
    $svc->depreciate($c, '2026-02');
    $other = Branch::create(['name' => ['en' => 'B2', 'ar' => 'ب2'], 'code' => 'B2', 'is_active' => true]);
    $svc->transfer($c, $other->id, '2026-03-01');
    expect($c->fresh()->branch_id)->toBe($other->id)->and(\App\Models\Erp\JournalLine::where('branch_id', $other->id)->whereHas('account', fn ($q) => $q->where('role_key', 'fixed_assets'))->sum('debit'))->toEqual(600);
});

it('serves every new page and enforces tenant isolation and permissions', function () {
    $base = ($this->asOwner)();
    ($this->bill)('100');
    $o = app(OwnerService::class)->save(['name' => 'Ali', 'ownership_percent' => 50]);
    $a = app(AssetService::class)->register(['name' => 'Van', 'acquisition_date' => now()->toDateString(), 'cost' => '100', 'useful_life_months' => 10]);
    foreach (["$base", "$base/parties/customer", "$base/parties/supplier", "$base/parties/supplier/ageing", "$base/parties/supplier/{$this->sup->id}", "$base/parties/customer/{$this->cust->id}",
        "$base/owners", "$base/owners/{$o->id}", "$base/assets", "$base/assets/{$a->id}"] as $url) {
        $this->get($url)->assertOk();
    }
    $this->get("$base")->assertSee(route('tenant.erp.owners.index'))->assertSee(route('tenant.erp.assets.index'))->assertSee(route('tenant.erp.parties.index', 'customer'));
    $this->get("$base/parties/other")->assertNotFound();
    $this->post("$base/parties/supplier/{$this->sup->id}/settle", ['amount' => 40, 'account' => 'cash', 'ref' => 'h1'])->assertRedirect();
    expect($this->ledger->position('supplier', $this->sup->id)['outstanding'])->toBe('60.0000');
    $this->post("$base/owners/{$o->id}/transactions", ['type' => 'contribution', 'amount' => 10, 'date' => now()->toDateString(), 'account' => 'cash', 'ref' => 'h2'])->assertRedirect();
    $this->post("$base/assets/{$a->id}/depreciate", ['period' => now()->format('Y-m')])->assertRedirect();

    // another tenant's ids are invisible on every page / action
    $other = Tenant::create(['slug' => 'oth', 'name' => 'O', 'country' => 'EG', 'currency_code' => 'EGP', 'currency_symbol' => 'E', 'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'UTC', 'is_active' => true]);
    $fs = Supplier::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'name' => 'FS', 'is_active' => true]);
    $fo = Owner::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'name' => 'FO', 'ownership_percent' => 10]);
    $fa = FixedAsset::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'name' => 'FA', 'acquisition_date' => '2026-01-01', 'cost' => 10, 'useful_life_months' => 5]);
    $this->get("$base/parties/supplier/{$fs->id}")->assertNotFound();
    $this->post("$base/parties/supplier/{$fs->id}/settle", ['amount' => 5, 'account' => 'cash', 'ref' => 'x'])->assertNotFound();
    $this->post("$base/parties/supplier/{$fs->id}/opening", ['amount' => 5, 'ref' => 'x'])->assertNotFound();
    $this->get("$base/owners/{$fo->id}")->assertNotFound();
    $this->put("$base/owners/{$fo->id}", ['name' => 'Z', 'ownership_percent' => 1])->assertNotFound();
    $this->post("$base/owners/{$fo->id}/transactions", ['type' => 'contribution', 'amount' => 5, 'date' => now()->toDateString(), 'account' => 'cash', 'ref' => 'x'])->assertNotFound();
    $this->get("$base/assets/{$fa->id}")->assertNotFound();
    $this->post("$base/assets/{$fa->id}/dispose", ['date' => now()->toDateString(), 'into' => 'cash'])->assertNotFound();
    $this->post("$base/assets/{$fa->id}/transfer", ['branch_id' => Branch::first()->id])->assertNotFound();
    expect($this->ledger->balances('supplier'))->toHaveCount(1)->and(Owner::count())->toBe(1)->and(FixedAsset::count())->toBe(1);

    // permission gates
    foreach (['erp.owners.manage', 'erp.owners.view', 'erp.assets.manage', 'erp.assets.view', 'erp.journals.create', 'erp.accounts.view'] as $p) {
        $this->owner->roles->each->revokePermissionTo($p);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->owner->unsetRelation('permissions')->unsetRelation('roles');
    $this->actingAs($this->owner->fresh(), 'tenant');
    $this->get("$base/owners")->assertForbidden();
    $this->post("$base/owners", ['name' => 'N', 'ownership_percent' => 1])->assertForbidden();
    $this->get("$base/assets")->assertForbidden();
    $this->post("$base/assets/{$a->id}/dispose", ['date' => now()->toDateString(), 'into' => 'cash'])->assertForbidden();
    $this->get("$base/parties/supplier")->assertForbidden();
    $this->post("$base/parties/supplier/{$this->sup->id}/settle", ['amount' => 5, 'account' => 'cash', 'ref' => 'zz'])->assertForbidden();
});
