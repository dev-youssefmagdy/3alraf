<?php

use App\DTOs\ExpenseData;
use App\DTOs\Orders\CheckoutData;
use App\DTOs\Orders\ReturnRequestData;
use App\DTOs\PurchaseOrderData;
use App\DTOs\StockAdjustmentData;
use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\City;
use App\Models\Erp\Account;
use App\Models\Erp\AuditLog;
use App\Models\Erp\BackfillRun;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\Period;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use App\Models\Admin;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Accounting\PeriodService;
use App\Services\Erp\Backfill\BackfillService;
use App\Services\Erp\Equity\ErpSettings;
use App\Services\ExpenseService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\ReturnService;
use App\Services\PurchaseService;
use App\Services\Stock\StockLedger;
use App\Services\StockAdjustmentService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    codUpfront(false);
    $this->branch = Branch::first();
    $mk = fn (string $slug, string $price, string $cost) => tap(Product::create(['type' => 'simple', 'name' => ['ar' => 'م', 'en' => $slug], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]), function ($p) use ($price, $cost) {
        listProduct($p, ['price' => $price, 'cost' => $cost, 'is_active' => true]);
        app(StockLedger::class)->record(Branch::first()->id, $p->id, 0, T::PURCHASE_IN, 100, $cost);
    });
    $this->product = $mk('pa', '100', '60');
    $this->actingAs(Admin::first(), 'admin');
    // these scenarios model a store whose history was recognised at admin confirmation (legacy policy, pinned for pre-existing ledgers by
    // migration 2026_12_17_100001); the default "shipped" policy is covered by ErpPostingTest / QaProbeErpAccountingTest (REG ERP-007/011)
    app(\App\Services\Erp\Equity\ErpSettings::class)->set('revenue_recognition', 'confirmed');
    $this->from = now()->subMonths(6)->startOfMonth()->toDateString();
    $this->before = now()->subMonths(8)->startOfMonth()->addDays(9)->toDateString();   // clearly before --from
    $this->after = fn (int $d) => now()->subMonths(4)->startOfMonth()->addDays($d)->toDateString();
    // a store that pre-dates the ERP: auto-posting is OFF, so everything below is "history" without journals
    app(ErpSettings::class)->set('auto_posting_enabled', false);

    $this->place = fn (int $qty = 2) => app(OrderPlacementService::class)->place(new CheckoutData(
        name: 'Ali', phone: '01000000100', cityId: City::first()->id, address: 'St', shippingCompanyId: ShippingCompany::first()->id,
        paymentMethodId: PaymentMethod::where('type', 'cod')->first()->id), [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => $qty]])['order'];
    $this->to = function (Order $o, S ...$steps) {
        foreach ($steps as $s) {
            app(OrderStatusService::class)->change($o, $s);
        }

        return $o->refresh();
    };
    // age an order's timestamps to a business date (history = confirmation date)
    $this->age = function (Order $o, string $date) {
        DB::table('orders')->where('id', $o->id)->update(['created_at' => $date.' 10:00:00', 'placed_at' => $date.' 10:00:00', 'delivered_at' => $o->delivered_at ? $date.' 12:00:00' : null]);
        DB::table('order_status_histories')->where('order_id', $o->id)->update(['created_at' => $date.' 11:00:00']);

        return $o->refresh();
    };
    $this->scenario = function () {
        $sup = Supplier::create(['name' => 'Sup', 'is_active' => true]);
        $po = fn (string $when, string $qty) => tap(app(PurchaseService::class)->create(new PurchaseOrderData($sup->id, $this->branch->id, $when, '20.00', '0', null,
            [['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => (int) $qty, 'unit_cost' => '50.00']], true)), fn ($p) => DB::table('purchase_orders')->where('id', $p->id)->update(['received_at' => $when.' 09:00:00']));
        $this->poOld = $po($this->before, '10');
        $this->poNew = $po(($this->after)(1), '5');
        $this->o1 = ($this->age)(($this->to)(($this->place)(), S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED), $this->before);      // delivered, COD unsettled -> opening carrier
        $this->o2 = ($this->age)(($this->to)(($this->place)(), S::CONFIRMED, S::PROCESSING, S::SHIPPED), $this->before);                       // in flight -> opening receivable
        $this->o3 = ($this->age)(($this->to)(($this->place)(3), S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED), ($this->after)(3));
        $this->o4 = ($this->age)(($this->to)(($this->place)(), S::CONFIRMED), ($this->after)(5));

        $this->o5 = ($this->to)(($this->place)(), S::CANCELLED);
        $this->o6 = ($this->age)(($this->to)(($this->place)(), S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED), ($this->after)(8));
        $this->o7 = ($this->place)();   // pending
        $svc = app(ReturnService::class);
        $item = $this->o3->items()->first();
        $r1 = $svc->approve($svc->request($this->o3, new ReturnRequestData(quantities: [$item->id => 1], reason: 'x', restock: true, refundShipping: false, returnShippingCost: '5.00')));
        DB::table('order_returns')->where('id', $r1->id)->update(['approved_at' => ($this->after)(10).' 10:00:00']);
        $item6 = $this->o6->items()->first();
        $r2 = $svc->approve($svc->request($this->o6->refresh(), new ReturnRequestData(quantities: [$item6->id => 2], reason: 'x', restock: false, refundShipping: false, returnShippingCost: '0')));
        DB::table('order_returns')->where('id', $r2->id)->update(['approved_at' => ($this->after)(12).' 10:00:00']);
        $cat = ExpenseCategory::first() ?? ExpenseCategory::create(['name' => ['ar' => 'ك', 'en' => 'Cat'], 'is_active' => true]);
        app(ExpenseService::class)->create(new ExpenseData($cat->id, null, '100.00', $this->before, 'old', null, false));
        $this->e2 = app(ExpenseService::class)->create(new ExpenseData($cat->id, null, '40.00', ($this->after)(6), 'new', null, false));
        $a = app(StockAdjustmentService::class)->create(new StockAdjustmentData($this->branch->id, AdjustmentType::DECREASE, AdjustmentReason::DAMAGED, null,
            [['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 2, 'unit_cost' => '60.00']]));
        DB::table('stock_adjustments')->where('id', $a->id)->update(['created_at' => ($this->after)(7).' 10:00:00']);
        expect(JournalEntry::count())->toBe(0);   // nothing was recorded: the flag is off
    };
    $this->backfill = fn (array $extra = []) => $this->artisan('erp:backfill', ['--from' => $this->from] + $extra);
});

it('backfills history idempotently at the documents\' own dates and the ledger reconciles', function () {
    ($this->scenario)();
    ($this->backfill)()->assertExitCode(0);
    $count = JournalEntry::count();
    expect($count)->toBeGreaterThan(8);
    // entry dates are business dates, not today
    $confirm = JournalEntry::where('idempotency_key', 'order:'.$this->o3->id.':confirm')->first();
    expect($confirm->entry_date->toDateString())->toBe(($this->after)(3))
        ->and(JournalEntry::where('idempotency_key', 'po:'.$this->poNew->id.':receive')->first()->entry_date->toDateString())->toBe(($this->after)(1))
        ->and(JournalEntry::where('source_type', 'expense')->where('source_id', $this->e2->id)->first()->entry_date->toDateString())->toBe(($this->after)(6));
    expect(JournalEntry::where('idempotency_key', 'order:'.$this->o1->id.':confirm')->exists())->toBeFalse()   // before --from: opening balances instead
        ->and(JournalEntry::where('idempotency_key', 'order:'.$this->o5->id.':confirm')->exists())->toBeFalse();   // cancelled: never posted
    // settings + run record
    expect(app(ErpSettings::class)->bool('auto_posting_enabled'))->toBeTrue()->and(app(ErpSettings::class)->get('opening_date'))->toBe($this->from);
    $run = BackfillRun::first();
    expect($run->status)->toBe('completed')->and($run->counts['orders']['created'])->toBe(3)->and($run->counts['orders']['failed'])->toBe(0);

    // second run: identical journal count, zero created
    ($this->backfill)()->assertExitCode(0);
    expect(JournalEntry::count())->toBe($count)->and(BackfillRun::count())->toBe(2)->and(BackfillRun::latest('id')->first()->counts['orders']['created'])->toBe(0);

    // opening balances
    $bal = fn (string $role) => (float) Account::where('role_key', $role)->value('current_balance');
    $o = fn (string $part) => JournalEntry::where('idempotency_key', 'opening:'.$this->from.':'.$part)->first();
    expect($o('receivable')->type)->toBe('opening')->and((float) $o('receivable')->total)->toBe((float) $this->o2->total)   // the in-flight order
        ->and((float) $o('carrier')->total)->toBe((float) $this->o1->cod_amount)
        ->and((float) $o('payable')->total)->toBe((float) $this->poOld->total_cost);
    expect(JournalEntry::where('type', 'opening')->get()->every(fn ($e) => $e->lines->sum('debit') == $e->lines->sum('credit')))->toBeTrue();
    // inventory ledger == stock valuation less COGS booked at confirmation for the confirmed-but-unshipped order (stock leaves at shipping)
    $stock = (float) DB::table('stock_levels')->selectRaw('SUM(ROUND(on_hand * avg_cost, 2)) v')->value('v');
    expect($bal('inventory'))->toBe(round($stock - (float) $this->o4->refresh()->cogs, 2));
    expect(app(\App\Services\Erp\Reports\LedgerReports::class)->trialBalance()['balanced'])->toBeTrue();
    // reconciliation: clean
    $this->artisan('erp:reconcile', [])->assertExitCode(0);
    $this->artisan('erp:reconcile', ['--json' => true])->assertExitCode(0);
});

it('reconcile exits non-zero when a mismatch is injected', function () {
    ($this->scenario)();
    ($this->backfill)()->assertExitCode(0);
    app(JournalService::class)->post(['entry_date' => now()->toDateString(), 'type' => 'manual', 'description' => 'oops', 'idempotency_key' => 'x:1'], [
        ['account' => 'cogs', 'debit' => '50'], ['account' => 'inventory', 'credit' => '50']]);
    $this->artisan('erp:reconcile', [])->assertExitCode(1);
});

it('never modifies historic business rows', function () {
    ($this->scenario)();
    $snap = fn () => collect(['orders', 'order_items', 'order_returns', 'purchase_orders', 'purchase_order_items', 'expenses', 'stock_levels', 'stock_movements', 'stock_adjustments', 'order_status_histories'])
        ->mapWithKeys(fn ($t) => [$t => md5(DB::table($t)->orderBy('id')->get()->toJson())])->all();
    $before = $snap();
    ($this->backfill)()->assertExitCode(0);
    ($this->backfill)()->assertExitCode(0);
    expect($snap())->toBe($before);
});

it('dry-run computes counts and writes nothing', function () {
    ($this->scenario)();
    $this->artisan('erp:backfill', ['--from' => $this->from, '--dry-run' => true])->expectsOutputToContain('would create')->assertExitCode(0);
    expect(JournalEntry::count())->toBe(0)->and(BackfillRun::count())->toBe(0)->and(Account::count())->toBe(0)->and(Period::count())->toBe(0)
        ->and(app(ErpSettings::class)->get('opening_date'))->toBeNull()->and(app(ErpSettings::class)->bool('auto_posting_enabled'))->toBeFalse()
        ->and(DB::table('erp_opening_orders')->count())->toBe(0);
    $res = app(BackfillService::class)->run($this->from, true);
    expect($res['counts']['orders']['created'])->toBe(3)->and($res['counts']['expenses']['created'])->toBe(1)->and($res['counts']['purchases']['created'])->toBe(1)
        ->and($res['opening']['payable'])->toBe('-'.number_format((float) $this->poOld->total_cost, 2, '.', ''));
    expect(JournalEntry::count())->toBe(0);
});

it('refuses to backfill into closed periods with a clear message and writes nothing', function () {
    ($this->scenario)();
    $ps = app(PeriodService::class);
    $ps->createYear((int) substr($this->from, 0, 4));
    foreach (Period::orderBy('starts_on')->where('ends_on', '<=', now()->subMonths(3)->toDateString())->get() as $p) {
        $ps->close($p, null);
    }
    ($this->backfill)()->expectsOutputToContain('closed')->assertExitCode(1);
    expect(JournalEntry::count())->toBe(0);
});

it('isolates per-document failures and records them', function () {
    ($this->scenario)();
    // an expense mapped to an unusable account fails; everything else goes through
    $acc = Account::count() ? null : app(\App\Services\Erp\Accounting\CoaTemplate::class)->seed();
    DB::table('expenses')->where('id', $this->e2->id)->update(['paid_from' => 'no_such_role']);
    ($this->backfill)()->assertExitCode(1);
    expect(AuditLog::where('action', 'backfill.failed')->where('subject_type', 'expenses')->where('subject_id', $this->e2->id)->exists())->toBeTrue()
        ->and(BackfillRun::first()->status)->toBe('partial')->and(BackfillRun::first()->counts['expenses']['failed'])->toBe(1)
        ->and(JournalEntry::where('idempotency_key', 'like', 'order:%:confirm')->count())->toBe(3)
        ->and(app(ErpSettings::class)->bool('auto_posting_enabled'))->toBeFalse();   // not enabled after a partial run
});

it('records nothing while auto-posting is disabled and posts once enabled', function () {
    $o = ($this->to)(($this->place)(), S::CONFIRMED);
    expect(JournalEntry::count())->toBe(0);
    app(ErpSettings::class)->set('auto_posting_enabled', true);
    $o2 = ($this->to)(($this->place)(), S::CONFIRMED);
    expect(JournalEntry::where('idempotency_key', 'order:'.$o2->id.':confirm')->exists())->toBeTrue()->and(JournalEntry::where('idempotency_key', 'order:'.$o->id.':confirm')->exists())->toBeFalse();
});

it('detects source documents without journals and posts them idempotently', function () {
    ($this->scenario)();
    ($this->backfill)()->assertExitCode(0);
    $svc = app(BackfillService::class);
    expect(collect($svc->missing())->sum('created'))->toBe(0);
    // documents created while the flag was off again (a gap after the backfill)
    app(ErpSettings::class)->set('auto_posting_enabled', false);
    $late = ($this->to)(($this->place)(), S::CONFIRMED);
    app(ExpenseService::class)->create(new ExpenseData(ExpenseCategory::first()->id, null, '12.00', now()->toDateString(), null, null, false));
    app(ErpSettings::class)->set('auto_posting_enabled', true);
    $m = $svc->missing();
    expect($m['orders']['created'])->toBe(1)->and($m['orders']['missing'])->toBe([$late->id])->and($m['expenses']['created'])->toBe(1);
    $n = JournalEntry::count();
    $svc->missing(true);
    expect(JournalEntry::count())->toBeGreaterThan($n)->and(collect($svc->missing())->sum('created'))->toBe(0);
    $n2 = JournalEntry::count();
    $svc->missing(true);
    expect(JournalEntry::count())->toBe($n2);
    $this->artisan('erp:reconcile', [])->assertExitCode(0);
});

it('an order confirmed before the opening date only posts its post-cutover movement', function () {
    ($this->scenario)();
    ($this->backfill)()->assertExitCode(0);
    $rev = (float) Account::where('role_key', 'sales_revenue')->value('current_balance');
    $n = JournalEntry::count();
    ($this->to)($this->o2->refresh(), S::DELIVERED);       // in-flight at the cutover, delivered later (live listener)
    expect((float) Account::where('role_key', 'sales_revenue')->value('current_balance'))->toBe($rev)   // no second revenue
        ->and(JournalEntry::count())->toBe($n + 1);
    $this->artisan('erp:reconcile', [])->assertExitCode(0);
});

it('migration page lists runs, reconciles, detects and posts missing journals, toggles auto-posting, only for erp.settings.manage', function () {
    ($this->scenario)();
    ($this->backfill)()->assertExitCode(0);
    $base = '/admin/erp';
    $this->get("$base")->assertOk()->assertSee(route('admin.erp.migration.index'), false);
    $this->get("$base/migration")->assertOk()->assertSee('data-runs', false)->assertSee($this->from)->assertSee('cli:')->assertSee('data-benign', false);
    $this->post("$base/migration/reconcile")->assertOk()->assertSee('data-recon', false)->assertSee('data-mismatches="0"', false);

    app(ErpSettings::class)->set('auto_posting_enabled', false);
    $late = ($this->to)(($this->place)(), S::CONFIRMED);
    $this->post("$base/migration/detect")->assertOk()->assertSee('data-missing', false)->assertSee((string) $late->id);
    $n = JournalEntry::count();
    $this->post("$base/migration/post-missing")->assertRedirect();
    expect(JournalEntry::count())->toBeGreaterThan($n)->and(JournalEntry::where('idempotency_key', 'order:'.$late->id.':confirm')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'backfill.post_missing')->exists())->toBeTrue();
    $this->put("$base/migration/auto-posting", ['enabled' => 1])->assertRedirect();
    expect(app(ErpSettings::class)->bool('auto_posting_enabled'))->toBeTrue();
    $this->put("$base/migration/auto-posting", ['enabled' => 0])->assertRedirect();
    expect(app(ErpSettings::class)->bool('auto_posting_enabled'))->toBeFalse()->and(AuditLog::where('action', 'auto_posting.disabled')->exists())->toBeTrue();

    // permission gate
    $u = Admin::query()->where('email', 'owner@egy.test')->first();
    $u->roles->each->revokePermissionTo('erp.settings.manage');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $u->unsetRelation('permissions')->unsetRelation('roles');
    $this->actingAs($u->fresh(), 'admin');
    $this->get("$base/migration")->assertForbidden();
    $this->post("$base/migration/post-missing")->assertForbidden();
    $this->put("$base/migration/auto-posting", ['enabled' => 1])->assertForbidden();
});

