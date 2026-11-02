<?php

use App\DTOs\Orders\CheckoutData;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Erp\Accounting\BranchTransferService;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Reports\DashboardService;
use App\Services\Erp\Reports\ErpReportFilter;
use App\Services\Erp\Reports\LedgerReports;
use App\Services\Erp\Reports\PartyLedger;
use App\Services\Erp\Reports\ReportCatalog;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    codUpfront($this->tenant, false);
    app()->instance('currentTenant', $this->tenant);
    app(CoaTemplate::class)->seed($this->tenant);
    $this->je = app(JournalService::class);
    $this->cat = app(ReportCatalog::class);
    $this->owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($this->owner, 'tenant');
    $this->base = 'http://egy.3alraf.test/admin/erp';
    $this->branch = Branch::first();
    $this->today = now()->toDateString();
    $this->old = now()->subDays(40)->toDateString();
    $this->sup = Supplier::create(['name' => 'Sup', 'is_active' => true]);
    $this->cust = Customer::create(['name' => 'Cus', 'email' => 'c@x.test', 'phone' => '01000000555', 'password' => 'secret123']);
    $this->tx = fn (string $dr, string $cr, string $amt, ?string $date = null, array $x = []) => $this->je->post(['entry_date' => $date ?? $this->today, 'description' => 'tx'] + ($x['entry'] ?? []), [
        ['account' => $dr, 'debit' => $amt] + ($x['dr'] ?? []), ['account' => $cr, 'credit' => $amt] + ($x['cr'] ?? [])]);
    $this->f = fn (array $o = []) => new ErpReportFilter($o['from'] ?? now()->startOfMonth()->toDateString(), $o['to'] ?? now()->toDateString(), $o['branch'] ?? null);
    $this->meta = fn (string $k, ?ErpReportFilter $f = null) => $this->cat->run($k, $f ?? ($this->f)(), null)['meta'];
    $this->rows = fn (string $k, ?ErpReportFilter $f = null, int $t = 0) => $this->cat->run($k, $f ?? ($this->f)(), null)['tables'][$t]['rows'];
    // a small but complete books scenario
    $this->scenario = function () {
        ($this->tx)('bank', 'owner_capital', '10000', $this->old);                                  // opening cash (financing)
        ($this->tx)('inventory', 'payable', '1000', null, ['cr' => ['party_type' => 'supplier', 'party_id' => $this->sup->id]]);
        ($this->tx)('receivable', 'sales_revenue', '500', null, ['dr' => ['party_type' => 'customer', 'party_id' => $this->cust->id]]);
        ($this->tx)('cogs', 'inventory', '200');
        ($this->tx)('rent', 'bank', '100');                                                          // operating out
        ($this->tx)('cash', 'sales_revenue', '300');                                                 // operating in
        ($this->tx)('fixed_assets', 'bank', '400');                                                  // investing out
    };
    $this->revoke = function (array $perms) {
        foreach ($perms as $p) {
            $this->owner->roles->each->revokePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->owner->unsetRelation('permissions')->unsetRelation('roles');
        $this->actingAs($this->owner->fresh(), 'tenant');
    };
});

it('trial balance, balance sheet and P&L reconcile with the general ledger', function () {
    ($this->scenario)();
    $r = app(LedgerReports::class);
    $tb = $r->trialBalance($this->today);
    expect($tb['balanced'])->toBeTrue()->and($tb['total_debit'])->toBe($tb['total_credit']);
    $bs = $r->balanceSheet($this->today);
    expect($bs['balanced'])->toBeTrue();
    $pl = $r->profitLoss(now()->startOfMonth()->toDateString(), $this->today);
    expect($pl['totals']['revenue'])->toBe('800.0000')->and($pl['totals']['cogs'])->toBe('200.0000')->and($pl['totals']['opex'])->toBe('100.0000')->and($pl['net_profit'])->toBe('500.0000');
    $gl = $r->generalLedger(Account::where('role_key', 'sales_revenue')->first(), $this->old, $this->today);
    expect($gl['closing'])->toBe('800.0000');
    // normalized exports of the statements carry the same figures
    expect(($this->meta)('profit-loss')['net_profit'])->toBe('500.0000')->and(($this->meta)('trial-balance')['balanced'])->toBeTrue()->and(($this->meta)('balance-sheet')['balanced'])->toBeTrue();
});

it('cash flow reconciles opening + net change = closing and classifies by counter account', function () {
    ($this->scenario)();
    $m = ($this->meta)('cash-flow');
    expect($m['opening'])->toBe('10000.0000')->and($m['net_change'])->toBe('-200.0000')->and($m['closing'])->toBe('9800.0000')->and($m['reconciled'])->toBeTrue()
        ->and($m['sections']['operating'])->toBe('200.0000')->and($m['sections']['investing'])->toBe('-400.0000')->and($m['sections']['financing'])->toBe('0.0000');
    // financing shows in the first month; branch filter keeps the identity
    $m2 = ($this->meta)('cash-flow', ($this->f)(['from' => $this->old]));
    expect($m2['opening'])->toBe('0.0000')->and($m2['sections']['financing'])->toBe('10000.0000')->and($m2['reconciled'])->toBeTrue();
    $b = ($this->meta)('cash-flow', ($this->f)(['branch' => $this->branch->id]));
    expect($b['reconciled'])->toBeTrue();
    // inter-account / inter-branch moves are neutral
    ($this->tx)('cash', 'bank', '50');
    expect(($this->meta)('cash-flow')['net_change'])->toBe('-200.0000');
});

it('receivable and payable reports match the party ledgers and control accounts', function () {
    ($this->scenario)();
    ($this->tx)('receivable', 'sales_revenue', '25'); // guest
    $led = app(PartyLedger::class);
    $ar = ($this->meta)('receivables');
    $custTotal = collect($led->balances('customer'))->reduce(fn ($c, $x) => bcadd($c, $x['balance'], 4), '0');
    expect($ar['parties'])->toBe($custTotal)->and($ar['parties'])->toBe('500.0000')->and($ar['unattributed'])->toBe('25.0000')->and($ar['control'])->toBe('525.0000');
    $ap = ($this->meta)('payables');
    expect($ap['parties'])->toBe('1000.0000')->and($ap['control'])->toBe('1000.0000')->and($ap['unattributed'])->toBe('0.0000');
    $rows = ($this->rows)('receivables');
    expect($rows[0]['party'])->toBe('Cus')->and($rows[0]['b0'])->toBe('500.0000');
    // customer filter
    $f = ($this->f)();
    $f->customerId = $this->cust->id;
    expect(count(($this->rows)('receivables', $f)))->toBe(2); // party + total
});

it('tax report shows VAT collected, reversed on returns and the net payable', function () {
    $je = $this->je;
    $je->post(['entry_date' => $this->today, 'source_type' => 'order', 'source_id' => 1], [['account' => 'receivable', 'debit' => '115'], ['account' => 'sales_revenue', 'credit' => '100'], ['account' => 'vat_payable', 'credit' => '15']]);
    $je->post(['entry_date' => $this->today, 'source_type' => 'order_return', 'source_id' => 1], [['account' => 'sales_returns', 'debit' => '20'], ['account' => 'vat_payable', 'debit' => '3'], ['account' => 'receivable', 'credit' => '23']]);
    $m = ($this->meta)('tax');
    expect($m['collected'])->toBe('15.0000')->and($m['returns'])->toBe('-3.0000')->and($m['closing'])->toBe('12.0000')->and($m['balance'])->toBe('12.0000')->and($m['reconciled'])->toBeTrue();
    // settlement of VAT is "other"
    ($this->tx)('vat_payable', 'bank', '12');
    expect(($this->meta)('tax')['closing'])->toBe('0.0000');
    // sales / returns reports read the same entries
    expect(($this->meta)('sales')['net_sales'])->toBe('100.0000')->and(($this->meta)('sales-returns')['returns'])->toBe('20.0000');
});

it('compares ledger inventory with the StockLedger valuation and shows the difference', function () {
    ($this->tx)('inventory', 'payable', '1000', null, ['dr' => ['branch_id' => $this->branch->id], 'cr' => ['party_type' => 'supplier', 'party_id' => $this->sup->id]]);
    $p = Product::create(['type' => 'simple', 'name' => ['ar' => 'م', 'en' => 'x'], 'slug' => 'x', 'sku' => 'X', 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
    TenantProduct::create(['product_id' => $p->id, 'price' => '100', 'cost' => '60', 'is_active' => true]);
    app(StockLedger::class)->record($this->branch->id, $p->id, 0, T::PURCHASE_IN, 10, '60');
    $m = ($this->meta)('inventory-valuation');
    expect($m['ledger'])->toBe('1000.0000')->and($m['stock'])->toBe('600.0000')->and($m['difference'])->toBe('400.0000');
    $rows = ($this->rows)('inventory-valuation');
    expect($rows[0]['difference'])->toBe('400.0000')->and($rows[0]['status'])->toBe(__('erp_rep.mismatch'));
    ($this->tx)('inventory_loss', 'inventory', '400', null, ['dr' => ['branch_id' => $this->branch->id], 'cr' => ['branch_id' => $this->branch->id]]);
    expect(($this->meta)('inventory-valuation')['difference'])->toBe('0.0000');
    expect(($this->meta)('cogs')['shrinkage'])->toBe('400.0000');
});

it('flags reconciliation mismatches against business records', function () {
    $p = Product::create(['type' => 'simple', 'name' => ['ar' => 'م', 'en' => 'pa'], 'slug' => 'pa', 'sku' => 'PA', 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
    TenantProduct::create(['product_id' => $p->id, 'price' => '100', 'cost' => '60', 'is_active' => true]);
    app(StockLedger::class)->record($this->branch->id, $p->id, 0, T::PURCHASE_IN, 100, '60');
    $o = app(OrderPlacementService::class)->place(new CheckoutData(name: 'Ali', phone: '01000000100', cityId: City::first()->id, address: 'St', shippingCompanyId: ShippingCompany::first()->id,
        paymentMethodId: PaymentMethod::where('type', 'cod')->first()->id), [['product_id' => $p->id, 'variant_id' => 0, 'qty' => 2]])['order'];
    app(OrderStatusService::class)->change($o, S::CONFIRMED);
    $ok = ($this->meta)('reconciliation')['ok'];
    expect($ok['revenue'])->toBeTrue()->and($ok['cogs'])->toBeTrue()->and($ok['receivable'])->toBeTrue()->and($ok['payable'])->toBeTrue()->and($ok['carrier'])->toBeTrue()
        ->and($ok['inventory'])->toBeFalse(); // stock was set up without a purchase document, the ledger knows nothing of it
    // deliberate mismatches: extra COGS and an unexplained receivable
    ($this->tx)('cogs', 'inventory', '50');
    ($this->tx)('receivable', 'other_income', '77');
    $r = ($this->meta)('reconciliation');
    expect($r['ok']['cogs'])->toBeFalse()->and($r['ok']['receivable'])->toBeFalse()->and($r['ok']['revenue'])->toBeTrue()->and($r['mismatches'])->toBeGreaterThanOrEqual(3);
    $rows = ($this->rows)('reconciliation');
    $cogs = collect($rows)->firstWhere('check', __('erp_rep.rc.cogs'));
    expect($cogs['difference'])->toBe('50.0000')->and($cogs['status'])->toBe(__('erp_rep.mismatch'));
    // unexplained supplier payable
    ($this->tx)('inventory', 'payable', '10');
    expect(($this->meta)('reconciliation')['ok']['payable'])->toBeFalse();
});

it('dashboard numbers equal the statements', function () {
    ($this->scenario)();
    $f = ($this->f)();
    $k = app(DashboardService::class)->kpis($f);
    $r = app(LedgerReports::class);
    $pl = $r->profitLoss($f->from, $f->to);
    $bs = $r->balanceSheet($f->to);
    $bsAmt = fn (string $sec, string $role) => collect($bs['sections'][$sec])->first(fn ($x) => $x['account']->role_key === $role)['amount'] ?? '0';
    expect($k['net_revenue'])->toBe($pl['totals']['revenue'])->and($k['cogs'])->toBe($pl['totals']['cogs'])->and($k['gross_profit'])->toBe($pl['gross_profit'])->and($k['opex'])->toBe($pl['totals']['opex'])
        ->and($k['net_profit'])->toBe($pl['net_profit'])->and($k['receivable'])->toBe($bsAmt('asset', 'receivable'))->and($k['payable'])->toBe($bsAmt('liability', 'payable'))
        ->and($k['inventory'])->toBe($bsAmt('asset', 'inventory'))->and($k['equity'])->toBe($bs['total_equity'])->and($k['liabilities'])->toBe($bs['totals']['liability'])
        ->and($k['cash'])->toBe(bcadd(bcadd($bsAmt('asset', 'bank'), $bsAmt('asset', 'cash'), 4), '0', 4))
        ->and($k['cash'])->toBe(($this->meta)('cash-flow')['closing']);
    $html = $this->get($this->base.'/dashboard')->assertOk()->getContent();
    expect($html)->toContain('data-kpi="net_profit"')->and($html)->toContain(number_format((float) $pl['net_profit'], 2))->and($html)->toContain('data-chart=');
    $this->get($this->base)->assertSee(route('tenant.erp.dashboard'))->assertSee(route('tenant.erp.branches.index'))->assertSee(route('tenant.erp.reports.index'))->assertSee(route('tenant.erp.reports.show', 'reconciliation'));
    // branch dashboard == branch statements
    ($this->tx)('cash', 'sales_revenue', '60', null, ['dr' => ['branch_id' => $this->branch->id], 'cr' => ['branch_id' => $this->branch->id]]);
    $fb = ($this->f)(['branch' => $this->branch->id]);
    expect(app(DashboardService::class)->kpis($fb)['net_profit'])->toBe($r->profitLoss($fb->from, $fb->to, $this->branch->id)['net_profit'])->and($r->profitLoss($fb->from, $fb->to, $this->branch->id)['net_profit'])->toBe('60.0000');
});

it('exports CSV and PDF with the report content and enforces the export permission', function () {
    ($this->scenario)();
    $res = $this->get($this->base.'/reports/profit-loss/export?format=csv')->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');
    $csv = $res->getContent();
    expect($csv)->toContain(__('erp_rep.t.profit-loss'))->and($csv)->toContain('800.00')->and($csv)->toContain('500.00')->and($csv)->toContain(__('erp.r.net'));
    foreach (array_keys(ReportCatalog::META) as $k) {
        if ($k === 'branch-profitability') {
            continue;
        }
        $this->get($this->base."/reports/$k")->assertOk();
        $this->get($this->base."/reports/$k/export")->assertOk();
    }
    $this->get($this->base.'/reports/branch-profitability/export')->assertOk()->assertSee(__('erp_rep.t.branch-profitability'), false);
    $pdf = $this->get($this->base.'/reports/cash-flow/export?format=pdf')->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('pdf')->and(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
    $this->get($this->base.'/reports')->assertOk()->assertSee(route('tenant.erp.reports.show', 'cash-flow'));
    ($this->revoke)(['erp.reports.export']);
    $this->get($this->base.'/reports/profit-loss/export')->assertForbidden();
    $this->get($this->base.'/reports/profit-loss')->assertOk()->assertDontSee('format=csv', false);
    $this->get($this->base.'/reports/nope')->assertNotFound();
    $this->get($this->base.'/reports/nope/export')->assertForbidden();
});

it('limits branch-specific views to users with erp.branches.view', function () {
    $b2 = Branch::create(['name' => ['en' => 'B2', 'ar' => 'ب2'], 'is_active' => true, 'is_default' => false]);
    ($this->tx)('cash', 'sales_revenue', '100', null, ['dr' => ['branch_id' => $this->branch->id], 'cr' => ['branch_id' => $this->branch->id]]);
    ($this->tx)('cash', 'sales_revenue', '40', null, ['dr' => ['branch_id' => $b2->id], 'cr' => ['branch_id' => $b2->id]]);
    $rows = ($this->rows)('branch-profitability');
    $by = collect($rows)->keyBy('branch');
    expect($by[(string) $this->branch->name]['net'])->toBe('100.0000')->and($by[(string) $b2->name]['net'])->toBe('40.0000')->and(collect($rows)->last()['net'])->toBe('140.0000');
    expect($this->get($this->base."/reports/profit-loss?branch_id={$b2->id}")->assertOk()->viewData('f')->branchId)->toBe($b2->id);
    $this->get($this->base.'/branches')->assertOk();
    ($this->revoke)(['erp.branches.view']);
    $this->get($this->base.'/reports/branch-profitability')->assertForbidden();
    $this->get($this->base.'/branches')->assertForbidden();
    $this->get($this->base."/reports/profit-loss?branch_id={$b2->id}")->assertOk()->assertViewHas('f', fn ($f) => $f->branchId === null)->assertViewHas('data', fn ($d) => $d['net_profit'] === '140.0000');
    $this->get($this->base."/dashboard?branch_id={$b2->id}")->assertOk()->assertViewHas('f', fn ($f) => $f->branchId === null);
    $this->get($this->base.'/reports')->assertDontSee(route('tenant.erp.reports.show', 'branch-profitability'));
});

it('transfers cash between branches without changing consolidated profit or cash', function () {
    $b2 = Branch::create(['name' => ['en' => 'B2', 'ar' => 'ب2'], 'is_active' => true, 'is_default' => false]);
    ($this->tx)('cash', 'branch_capital', '1000', null, ['dr' => ['branch_id' => $this->branch->id], 'cr' => ['branch_id' => $this->branch->id]]);
    ($this->tx)('cash', 'sales_revenue', '10', null, ['dr' => ['branch_id' => $this->branch->id], 'cr' => ['branch_id' => $this->branch->id]]);
    // an older tenant that never got the clearing account: ensureRoles tops it up lazily
    Account::where('role_key', 'branch_transfer')->forceDelete();
    app(\App\Services\Erp\Accounting\AccountResolver::class)->flush();
    $before = ($this->meta)('cash-flow')['closing'];
    $this->post($this->base.'/branches/transfer', ['from_branch' => $this->branch->id, 'to_branch' => $b2->id, 'amount' => '250.00', 'ref' => 'r1', 'role' => 'cash'])->assertRedirect()->assertSessionHas('status');
    $this->post($this->base.'/branches/transfer', ['from_branch' => $this->branch->id, 'to_branch' => $b2->id, 'amount' => '250.00', 'ref' => 'r1', 'role' => 'cash'])->assertRedirect(); // replay
    expect(JournalEntry::where('source_type', 'branch_cash_transfer')->count())->toBe(1)->and(Account::where('role_key', 'branch_transfer')->count())->toBe(1);
    $rows = collect(app(\App\Services\Erp\Reports\BranchReports::class)->overview(($this->f)()))->keyBy('id');
    expect($rows[$this->branch->id]['cash'])->toBe('760.0000')->and($rows[$b2->id]['cash'])->toBe('250.0000')->and($rows[$this->branch->id]['cash_out'])->toBe('250.0000')->and($rows[$b2->id]['cash_in'])->toBe('250.0000');
    expect(($this->meta)('cash-flow')['closing'])->toBe($before)->and(app(LedgerReports::class)->balancesByRole()['branch_transfer'] ?? '0.0000')->toBe('0.0000')
        ->and(app(LedgerReports::class)->profitLoss(null, null)['net_profit'])->toBe('10.0000')->and(($this->meta)('cash-flow')['net_change'])->toBe($before);
    // per-branch cash flow shows the transfer as an internal move
    expect(($this->meta)('cash-flow', ($this->f)(['branch' => $b2->id]))['sections']['transfers'])->toBe('250.0000');
    $this->get($this->base.'/branches')->assertOk()->assertSee('250.00');
    expect(fn () => app(BranchTransferService::class)->cash($this->branch->id, $this->branch->id, '5'))->toThrow(\App\Exceptions\AccountingException::class);
});

it('keeps reports tenant-isolated', function () {
    ($this->scenario)();
    $other = Tenant::create(['slug' => 'oth', 'name' => 'O', 'country' => 'EG', 'currency_code' => 'EGP', 'currency_symbol' => 'E', 'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'UTC', 'is_active' => true]);
    app(CoaTemplate::class)->seed($other);
    $ob = Branch::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'name' => ['en' => 'OB', 'ar' => 'ب'], 'is_active' => true, 'is_default' => true]);
    app()->instance('currentTenant', $other);
    app(\App\Services\Erp\Accounting\AccountResolver::class)->flush();
    $this->je->post(['entry_date' => $this->today, 'description' => 'foreign'], [['account' => 'bank', 'debit' => '777', 'branch_id' => $ob->id], ['account' => 'sales_revenue', 'credit' => '777', 'branch_id' => $ob->id]]);
    app()->instance('currentTenant', $this->tenant);
    app(\App\Services\Erp\Accounting\AccountResolver::class)->flush();
    expect(($this->meta)('profit-loss')['net_profit'])->toBe('500.0000')->and(($this->meta)('cash-flow')['closing'])->toBe('9800.0000');
    $csv = $this->get($this->base.'/reports/journal-entries/export?from='.$this->old)->assertOk()->getContent();
    expect($csv)->not->toContain('777')->and(count(($this->rows)('journal-entries', ($this->f)(['from' => $this->old]))))->toBe(2 * 7 + 1);
    $this->get($this->base."/reports/profit-loss?branch_id={$ob->id}")->assertNotFound();
    $this->get($this->base.'/branches')->assertOk()->assertDontSee('OB');
    $this->post($this->base.'/branches/transfer', ['from_branch' => $this->branch->id, 'to_branch' => $ob->id, 'amount' => '5'])->assertNotFound();
});

it('keeps report query counts bounded (no N+1)', function () {
    $branches = [$this->branch, Branch::create(['name' => ['en' => 'B2', 'ar' => 'ب2'], 'is_active' => true, 'is_default' => false])];
    for ($i = 0; $i < 12; $i++) {
        $s = Supplier::create(['name' => 'S'.$i, 'is_active' => true]);
        $c = Customer::create(['name' => 'C'.$i, 'email' => "c$i@x.test", 'phone' => '0100000'.str_pad((string) (700 + $i), 4, '0', STR_PAD_LEFT), 'password' => 'secret123']);
        $b = $branches[$i % 2]->id;
        ($this->tx)('inventory', 'payable', '100', null, ['entry' => ['source_type' => 'purchase_order', 'source_id' => $i + 1], 'dr' => ['branch_id' => $b], 'cr' => ['party_type' => 'supplier', 'party_id' => $s->id, 'branch_id' => $b]]);
        ($this->tx)('receivable', 'sales_revenue', '50', null, ['entry' => ['source_type' => 'order', 'source_id' => 0], 'dr' => ['party_type' => 'customer', 'party_id' => $c->id, 'branch_id' => $b], 'cr' => ['branch_id' => $b]]);
        ($this->tx)('rent', 'bank', '5', null, ['dr' => ['branch_id' => $b], 'cr' => ['branch_id' => $b]]);
    }
    $f = ($this->f)(['from' => now()->subDays(60)->toDateString()]);
    foreach (array_keys(ReportCatalog::META) as $k) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->cat->run($k, $f, null);
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();
        expect($n)->toBeLessThan(40, "$k used $n queries");
    }
    DB::flushQueryLog();
    DB::enableQueryLog();
    app(DashboardService::class)->run($f);
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($n)->toBeLessThan(40, "dashboard used $n queries");
});
