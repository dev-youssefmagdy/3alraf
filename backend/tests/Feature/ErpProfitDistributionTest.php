<?php

use App\Exceptions\AccountingException;
use App\Models\Erp\Account;
use App\Models\Erp\FiscalYear;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\Owner;
use App\Models\Erp\Period;
use App\Models\Erp\ProfitDistribution;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Accounting\PeriodService;
use App\Services\Erp\Equity\ErpSettings;
use App\Services\Erp\Equity\ProfitDistributionService;
use App\Services\Erp\Equity\ProfitService;
use App\Services\Erp\Owners\OwnerService;
use App\Services\Erp\Reports\LedgerReports;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    app(CoaTemplate::class)->seed($this->tenant);
    $this->je = app(JournalService::class);
    $this->profit = app(ProfitService::class);
    $this->dist = app(ProfitDistributionService::class);
    $this->settings = app(ErpSettings::class);
    $this->owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($this->owner, 'tenant');
    $this->today = now()->toDateString();
    $this->from = now()->startOfMonth()->toDateString();
    $this->bal = fn (string $role) => (string) Account::where('role_key', $role)->value('current_balance');
    // Dr $dr / Cr $cr
    $this->tx = fn (string $dr, string $cr, string $amt, ?string $date = null, array $extra = []) => $this->je->post(['entry_date' => $date ?? $this->today, 'description' => 'tx'], [
        ['account' => $dr, 'debit' => $amt] + $extra, ['account' => $cr, 'credit' => $amt] + $extra]);
    $this->owners = function (array $pcts) {
        $svc = app(OwnerService::class);
        foreach ($pcts as $name => $p) {
            $svc->save(['name' => $name, 'ownership_percent' => $p, 'is_eligible' => true]);
        }
    };
    $this->run = fn (array $o = []) => $this->dist->create(array_merge(['from' => $this->from, 'to' => $this->today, 'acknowledge_open' => true], $o), $this->owner->id);
    $this->full = function (ProfitDistribution $d) {
        $d = $this->dist->approve($d, $this->owner->id);

        return $this->dist->post($d, $this->owner->id);
    };
    $this->second = function () {
        $a = new TenantAdmin(['tenant_id' => $this->tenant->id, 'name' => 'Second', 'phone' => '20100000999', 'password' => 'secret123', 'is_active' => true]);
        $a->save();
        $a->assignRole(\Spatie\Permission\Models\Role::where(['name' => 'tenant-owner', 'guard_name' => 'tenant'])->first());

        return $a;
    };
});

it('computes net profit from a worked example using posted lines only', function () {
    ($this->tx)('bank', 'sales_revenue', '10000');
    ($this->tx)('sales_returns', 'bank', '500');
    ($this->tx)('sales_discounts', 'bank', '200');
    ($this->tx)('cogs', 'inventory', '4000');
    ($this->tx)('rent', 'bank', '1000');
    ($this->tx)('marketing', 'bank', '800');
    ($this->tx)('bank', 'other_income', '300');
    ($this->tx)('fx_loss', 'bank', '100');
    $this->je->draft(['entry_date' => $this->today], [['account' => 'rent', 'debit' => '9999'], ['account' => 'bank', 'credit' => '9999']]); // drafts never count
    $c = $this->profit->compute($this->from, $this->today);
    expect($c['gross_revenue'])->toBe('10000.0000')->and($c['returns_discounts'])->toBe('700.0000')->and($c['net_revenue'])->toBe('9300.0000')
        ->and($c['cogs'])->toBe('4000.0000')->and($c['gross_profit'])->toBe('5300.0000')->and($c['opex'])->toBe('1800.0000')->and($c['operating_profit'])->toBe('3500.0000')
        ->and($c['other_income'])->toBe('300.0000')->and($c['other_expense'])->toBe('100.0000')->and($c['profit_before_tax'])->toBe('3700.0000')->and($c['tax'])->toBe('0.00')
        ->and($c['net_profit'])->toBe('3700.0000')->and($c['ledger_profit'])->toBe('3700.0000')->and($c['cash_total'])->toBe('7700.0000')->and($c['distributable'])->toBe('3700.00');
    expect(app(LedgerReports::class)->profitLoss($this->from, $this->today)['net_profit'])->toBe('3700.0000');
    // tax estimate
    $this->settings->set('tax_rate', '10');
    $c = $this->profit->compute($this->from, $this->today);
    expect($c['tax'])->toBe('370.00')->and($c['net_profit'])->toBe('3330.0000');
});

it('caps the distributable amount by cash when profit is not cash', function () {
    ($this->tx)('receivable', 'sales_revenue', '5000'); // profit but no cash
    ($this->tx)('bank', 'owner_capital', '100');
    ($this->tx)('inventory', 'payable', '80');          // supplier due
    $c = $this->profit->compute($this->from, $this->today);
    expect($c['distributable_profit'])->toBe('5000.00')->and($c['liabilities_due'])->toBe('80.0000')->and($c['distributable_cash'])->toBe('20.00')
        ->and($c['distributable'])->toBe('20.00')->and($c['cash_limited'])->toBeTrue();
    ($this->owners)(['A' => 100]);
    $d = ($this->run)();
    expect((string) $d->distributed_amount)->toBe('20.0000');
    $this->settings->set('distribution_min_cash_buffer', '15');
    expect($this->profit->compute($this->from, $this->today)['distributable'])->toBe('5.00');
});

it('carries prior losses and applies the reserve percent after them', function () {
    ($this->tx)('rent', 'bank', '600', now()->subDays(40)->toDateString());
    ($this->tx)('bank', 'owner_capital', '5000');
    ($this->tx)('bank', 'sales_revenue', '1000');
    ($this->owners)(['A' => 100]);
    $this->settings->set('reserve_percent', '10');
    $c = $this->profit->compute($this->from, $this->today);
    expect($c['prior_retained'])->toBe('-600.0000')->and($c['prior_losses'])->toBe('600.0000')->and($c['profit_after_losses'])->toBe('400.0000')
        ->and($c['reserve_amount'])->toBe('40.00')->and($c['distributable_profit'])->toBe('360.00');
    $d = ($this->run)();
    expect((string) $d->distributed_amount)->toBe('360.0000');
});

it('splits by ownership percent to the cent giving the remainder to the largest owner, with eligibility, overrides and partial retention', function () {
    ($this->owners)(['A' => 33.3333, 'B' => 33.3333, 'C' => 33.3334]);
    Owner::where('name', 'B')->update(['is_eligible' => false]);
    ($this->tx)('bank', 'sales_revenue', '100');
    $s = $this->dist->split('100.00');
    // B is not eligible: A and C share by their relative percent, remainder to the largest (C)
    expect(collect($s)->pluck('owner.name')->all())->toBe(['A', 'C'])->and(collect($s)->sum(fn ($r) => (float) $r['amount']))->toBe(100.0);
    Owner::where('name', 'B')->update(['is_eligible' => true]);
    $s = $this->dist->split('100.00');
    expect(collect($s)->pluck('amount', 'owner.name')->all())->toBe(['A' => '33.33', 'B' => '33.33', 'C' => '33.34']);
    $d = ($this->run)(['retain_percent' => 25]);
    expect((string) $d->distributed_amount)->toBe('75.0000')->and($d->lines->sum('amount'))->toEqual(75)->and((string) $d->retained_after)->toBe('25.0000');
    $this->dist->cancel($d, $this->owner->id);
    // custom amounts must add up to the total
    $ids = Owner::orderBy('id')->pluck('id', 'name');
    expect(fn () => ($this->run)(['overrides' => [$ids['A'] => 10, $ids['B'] => 10]]))->toThrow(AccountingException::class);
    $d = ($this->run)(['overrides' => [$ids['A'] => 60, $ids['C'] => 40]]);
    expect($d->lines->pluck('amount', 'owner_id')->map(fn ($v) => (string) $v)->all())->toBe([$ids['A'] => '60.0000', $ids['C'] => '40.0000']);
    // retained amount beyond the distributable is refused
    $this->dist->cancel($d, $this->owner->id);
    expect(fn () => ($this->run)(['retain_amount' => 100]))->toThrow(AccountingException::class);
});

it('blocks duplicate and overlapping distributions and frees the period on cancel', function () {
    ($this->owners)(['A' => 100]);
    ($this->tx)('bank', 'sales_revenue', '100');
    $d = ($this->run)();
    expect(fn () => ($this->run)())->toThrow(AccountingException::class);
    expect(fn () => ($this->run)(['from' => $this->today]))->toThrow(AccountingException::class); // overlapping sub-period
    // the DB constraint is the last line of defence
    expect(fn () => DB::table('erp_profit_distributions')->insert(['tenant_id' => $this->tenant->id, 'period_start' => $this->from, 'period_end' => $this->today, 'active_key' => $d->active_key]))->toThrow(\Illuminate\Database\QueryException::class);
    $d = ($this->full)($d);
    expect(fn () => ($this->run)())->toThrow(AccountingException::class);
    $this->dist->cancel($d, $this->owner->id, 'oops'); // posted -> reversal
    expect($d->fresh()->status)->toBe('cancelled')->and(($this->bal)('distributions_payable'))->toBe('0.0000')->and(($this->bal)('retained_earnings'))->toBe('0.0000');
    expect(ProfitDistribution::where('status', 'cancelled')->count())->toBe(1);
    expect(($this->run)()->status)->toBe('draft'); // period is free again
});

it('posts accrual, reserve and the profit transfer correctly and keeps statements reconciled', function () {
    ($this->owners)(['A' => 60, 'B' => 40]);
    ($this->tx)('bank', 'sales_revenue', '1000');
    ($this->tx)('rent', 'bank', '200');
    $this->settings->set('reserve_percent', '10');
    $d = ($this->run)();
    expect((string) $d->net_profit)->toBe('800.0000')->and((string) $d->reserve_amount)->toBe('80.0000')->and((string) $d->distributed_amount)->toBe('720.0000');
    $d = ($this->full)($d);
    $r = app(LedgerReports::class);
    // profit 800 moved IS->RE (Dr IS / Cr RE), then RE pays 720 to owners (payable) and 80 to reserves
    expect(($this->bal)('retained_earnings'))->toBe('0.0000')->and(($this->bal)('distributions_payable'))->toBe('720.0000')->and(($this->bal)('reserves'))->toBe('80.0000')
        ->and(($this->bal)('income_summary'))->toBe('-800.0000');
    expect($d->transfer_entry_id)->not->toBeNull()->and($d->entry->type)->toBe('auto');
    expect($r->trialBalance()['balanced'])->toBeTrue();
    $bs = $r->balanceSheet();
    expect($bs['balanced'])->toBeTrue()->and($bs['current_earnings'])->toBe('800.0000')->and($bs['totals']['equity'])->toBe('-720.0000'); // 80 reserve + (-800) IS ; RE 0 ; earnings +800
    $a = Owner::where('name', 'A')->first();
    expect(app(OwnerService::class)->equity()[$a->id]['distributions'])->toBe('-432.0000');
    $st = app(OwnerService::class)->statement($a, $this->from, $this->today);
    expect($st['closing'])->toBe('-432.0000');
    // profit stays computable and a second run of the same data finds nothing left to double distribute
    expect($r->profitLoss($this->from, $this->today)['net_profit'])->toBe('800.0000');
});

it('requires payments to be backed by cash and refuses overdraw and double payment', function () {
    ($this->owners)(['A' => 100]);
    ($this->tx)('receivable', 'sales_revenue', '500');
    ($this->tx)('bank', 'owner_capital', '300');
    $d = ($this->full)(($this->run)());
    expect((string) $d->distributed_amount)->toBe('300.0000');
    $line = $d->lines->first();
    expect(fn () => $this->dist->pay($line, 'cash', $this->owner->id))->toThrow(AccountingException::class); // empty till
    $paid = $this->dist->pay($line, 'bank', $this->owner->id);
    expect($paid->paid)->toBeTrue()->and(($this->bal)('bank'))->toBe('0.0000')->and(($this->bal)('distributions_payable'))->toBe('0.0000');
    expect(fn () => $this->dist->pay($line->fresh(), 'bank', $this->owner->id))->toThrow(AccountingException::class);
    expect(fn () => $this->dist->cancel($d->fresh(), $this->owner->id))->toThrow(AccountingException::class); // paid shares
});

it('separates creator and approver when the setting is on', function () {
    ($this->owners)(['A' => 100]);
    ($this->tx)('bank', 'sales_revenue', '100');
    $this->settings->set('distribution_requires_second_approver', true);
    $d = ($this->run)();
    expect(fn () => $this->dist->approve($d, $this->owner->id))->toThrow(AccountingException::class);
    $second = ($this->second)();
    expect($this->dist->approve($d->fresh(), $second->id)->status)->toBe('approved');
    $this->dist->post($d->fresh(), $second->id);
    expect($d->fresh()->status)->toBe('posted');
});

it('blocks distribution on draft journals, an unbalanced ledger and an unacknowledged open period', function () {
    ($this->owners)(['A' => 100]);
    $e = ($this->tx)('bank', 'sales_revenue', '100');
    expect(fn () => $this->dist->create(['from' => $this->from, 'to' => $this->today], $this->owner->id))->toThrow(AccountingException::class); // open period, no acknowledge
    $draft = $this->je->draft(['entry_date' => $this->today], [['account' => 'rent', 'debit' => '5'], ['account' => 'bank', 'credit' => '5']]);
    expect(fn () => ($this->run)())->toThrow(AccountingException::class);
    $this->je->deleteDraft($draft);
    DB::table('erp_journal_lines')->insert(['tenant_id' => $this->tenant->id, 'entry_id' => $e->id, 'account_id' => Account::where('role_key', 'rent')->value('id'), 'debit' => 7, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()]);
    expect(app(LedgerReports::class)->trialBalance()['balanced'])->toBeFalse();
    expect(fn () => ($this->run)())->toThrow(AccountingException::class);
    expect(ProfitDistribution::count())->toBe(0);
});

it('re-validates against the ledger at post time (profit cannot be double spent)', function () {
    ($this->owners)(['A' => 100]);
    ($this->tx)('bank', 'sales_revenue', '1000');
    $d = $this->dist->approve(($this->run)(), $this->owner->id);
    ($this->tx)('rent', 'bank', '900'); // profit collapses after approval
    expect(fn () => $this->dist->post($d->fresh(), $this->owner->id))->toThrow(AccountingException::class);
    expect($d->fresh()->status)->toBe('approved')->and(($this->bal)('distributions_payable'))->toBe('0.0000');
});

it('closes a year keeping P&L computable, statements balanced, with double-close guard and audited reopen', function () {
    $y = now()->year - 1;
    ($this->tx)('bank', 'sales_revenue', '1000', "$y-03-10");
    ($this->tx)('cogs', 'inventory', '300', "$y-04-10");
    ($this->tx)('rent', 'bank', '200', "$y-05-10");
    $ps = app(PeriodService::class);
    $fy = FiscalYear::where('starts_on', "$y-01-01")->firstOrFail();
    $r = app(LedgerReports::class);
    expect($r->profitLoss("$y-01-01", "$y-12-31")['net_profit'])->toBe('500.0000');
    $ps->closeYear($fy, $this->owner->id);
    expect($fy->fresh()->status)->toBe('closed')->and(Period::whereYear('starts_on', $y)->where('status', 'open')->count())->toBe(0);
    foreach (['sales_revenue', 'cogs', 'rent'] as $role) {
        expect(($this->bal)($role))->toBe('0.0000');
    }
    expect(($this->bal)('retained_earnings'))->toBe('500.0000')->and(($this->bal)('income_summary'))->toBe('0.0000');
    expect(JournalEntry::where('type', 'closing')->count())->toBe(2);
    // P&L of the closed year still computable (closing entries excluded); balance sheet includes them
    expect($r->profitLoss("$y-01-01", "$y-12-31")['net_profit'])->toBe('500.0000')->and($r->profitLoss("$y-01-01", "$y-12-31")['totals']['revenue'])->toBe('1000.0000');
    $bs = $r->balanceSheet("$y-12-31");
    expect($bs['balanced'])->toBeTrue()->and($bs['current_earnings'])->toBe('0.0000')->and($bs['totals']['equity'])->toBe('500.0000');
    expect($r->trialBalance()['balanced'])->toBeTrue();
    // guards
    expect(fn () => $ps->closeYear($fy->fresh(), $this->owner->id))->toThrow(AccountingException::class);
    expect(fn () => ($this->tx)('bank', 'sales_revenue', '5', "$y-06-01"))->toThrow(AccountingException::class);
    expect(fn () => $ps->reopen(Period::where('starts_on', "$y-06-01")->first(), $this->owner->id, 'x'))->toThrow(AccountingException::class);
    expect(fn () => $ps->reopenYear($fy->fresh(), $this->owner->id, ' '))->toThrow(AccountingException::class);
    $ps->reopenYear($fy->fresh(), $this->owner->id, 'late invoice');
    expect($fy->fresh()->status)->toBe('open')->and(($this->bal)('retained_earnings'))->toBe('0.0000')->and(($this->bal)('sales_revenue'))->toBe('1000.0000');
    expect($r->profitLoss("$y-01-01", "$y-12-31")['net_profit'])->toBe('500.0000');
    $bs = $r->balanceSheet("$y-12-31");
    expect($bs['balanced'])->toBeTrue()->and($bs['current_earnings'])->toBe('500.0000');
    ($this->tx)('bank', 'sales_revenue', '100', "$y-06-01"); // posting is possible again
    $ps->closeYear($fy->fresh(), $this->owner->id);
    expect(($this->bal)('retained_earnings'))->toBe('600.0000')->and($r->balanceSheet("$y-12-31")['balanced'])->toBeTrue();
});

it('distributes the profit of a closed year without moving it twice', function () {
    $y = now()->year - 1;
    ($this->owners)(['A' => 100]);
    ($this->tx)('bank', 'sales_revenue', '1000', "$y-03-10");
    ($this->tx)('rent', 'bank', '200', "$y-05-10");
    app(PeriodService::class)->closeYear(FiscalYear::where('starts_on', "$y-01-01")->first(), $this->owner->id);
    $d = $this->dist->create(['from' => "$y-01-01", 'to' => "$y-12-31"], $this->owner->id); // closed periods: no acknowledgement needed
    expect((string) $d->net_profit)->toBe('800.0000');
    $d = ($this->full)($d);
    expect($d->transfer_entry_id)->toBeNull()->and(($this->bal)('retained_earnings'))->toBe('0.0000')->and(($this->bal)('distributions_payable'))->toBe('800.0000');
    expect(app(LedgerReports::class)->balanceSheet()['balanced'])->toBeTrue();
    // the next year starts with no carried loss and nothing new to distribute
    expect($this->profit->compute($this->from, $this->today)['distributable_profit'])->toBe('0.00');
    expect(fn () => app(PeriodService::class)->reopenYear(FiscalYear::where('starts_on', "$y-01-01")->first(), $this->owner->id, 'r'))->toThrow(AccountingException::class);
});

it('serves the pages, enforces permissions and tenant isolation', function () {
    $base = 'http://egy.3alraf.test/admin/erp';
    ($this->owners)(['A' => 100]);
    ($this->tx)('bank', 'sales_revenue', '100');
    $d = ($this->full)(($this->run)());
    $o = Owner::first();
    foreach (['profits', "profits/{$d->id}", 'profits/report', "profits/owner/{$o->id}/statement", 'settings', 'periods', "owners/{$o->id}", ''] as $u) {
        $this->get("$base/$u")->assertOk();
    }
    $this->get($base)->assertSee(route('tenant.erp.profits.index'))->assertSee(route('tenant.erp.settings.edit'));
    $this->put("$base/settings", ['reserve_percent' => 5, 'distribution_min_cash_buffer' => 10, 'tax_rate' => 0, 'distribution_requires_second_approver' => 1])->assertRedirect();
    expect($this->settings->get('reserve_percent'))->toBe('5')->and($this->settings->bool('distribution_requires_second_approver'))->toBeTrue();
    $this->post("$base/profits/{$d->id}/lines/{$d->lines->first()->id}/pay", ['account' => 'bank'])->assertRedirect();
    expect($d->lines->first()->fresh()->paid)->toBeTrue();

    $other = Tenant::create(['slug' => 'oth', 'name' => 'O', 'country' => 'EG', 'currency_code' => 'EGP', 'currency_symbol' => 'E', 'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'UTC', 'is_active' => true]);
    $fo = Owner::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'name' => 'FO', 'ownership_percent' => 10]);
    $fd = ProfitDistribution::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'period_start' => '2025-01-01', 'period_end' => '2025-01-31', 'status' => 'draft']);
    $this->get("$base/profits/{$fd->id}")->assertNotFound();
    $this->post("$base/profits/{$fd->id}/approve")->assertNotFound();
    $this->post("$base/profits/{$fd->id}/cancel")->assertNotFound();
    $this->get("$base/profits/owner/{$fo->id}/statement")->assertNotFound();
    expect($fd->fresh()->status)->toBe('draft');

    foreach (['erp.profits.distribute', 'erp.settings.manage'] as $p) {
        $this->owner->roles->each->revokePermissionTo($p);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->owner->unsetRelation('permissions')->unsetRelation('roles');
    $this->actingAs($this->owner->fresh(), 'tenant');
    $this->get("$base/profits")->assertOk(); // view stays
    $this->post("$base/profits", ['from' => $this->from, 'to' => $this->today])->assertForbidden();
    $this->post("$base/profits/{$d->id}/approve")->assertForbidden();
    $this->post("$base/profits/{$d->id}/cancel")->assertForbidden();
    $this->get("$base/settings")->assertForbidden();
    $this->put("$base/settings", ['reserve_percent' => 1, 'distribution_min_cash_buffer' => 0, 'tax_rate' => 0])->assertForbidden();
    $this->owner->roles->each->revokePermissionTo('erp.profits.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->owner->fresh(), 'tenant');
    $this->get("$base/profits")->assertForbidden();
});
