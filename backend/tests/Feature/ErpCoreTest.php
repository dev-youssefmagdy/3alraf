<?php

use App\Exceptions\AccountingException;
use App\Models\Erp\Account;
use App\Models\Erp\JournalEntry;
use App\Models\Erp\Period;
use App\Models\Admin;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Accounting\PeriodService;
use App\Services\Erp\Reports\LedgerReports;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    app(CoaTemplate::class)->seed();
    $this->svc = app(JournalService::class);
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->sale = fn (array $o = []) => $this->svc->post(array_merge(['entry_date' => now()->toDateString(), 'description' => 't'], $o), [
        ['account' => 'cash', 'debit' => '100'], ['account' => 'sales_revenue', 'credit' => '100'],
    ]);
});

it('posts balanced entries and updates account balances', function () {
    $e = ($this->sale)();
    expect($e->status)->toBe('posted')->and($e->number)->toBe('JE-000001');
    expect(Account::where('role_key', 'cash')->first()->current_balance)->toBe('100.0000');
});

it('rejects unbalanced entries', function () {
    $this->svc->post(['entry_date' => now()->toDateString()], [['account' => 'cash', 'debit' => '100'], ['account' => 'sales_revenue', 'credit' => '99']]);
})->throws(AccountingException::class);

it('is idempotent per key', function () {
    $a = ($this->sale)(['idempotency_key' => 'order:1']);
    $b = ($this->sale)(['idempotency_key' => 'order:1']);
    expect($b->id)->toBe($a->id)->and(JournalEntry::count())->toBe(1);
    expect(Account::where('role_key', 'cash')->first()->current_balance)->toBe('100.0000');
});

it('rolls back everything when posting fails', function () {
    Account::where('role_key', 'sales_revenue')->update(['is_active' => false]);
    try {
        $this->svc->post(['entry_date' => now()->toDateString()], [['account' => 'cash', 'debit' => '5'], ['account' => 17 * 0 + Account::where('role_key', 'sales_revenue')->value('id'), 'credit' => '5']]);
    } catch (AccountingException) {
    }
    expect(JournalEntry::count())->toBe(0)->and(Account::where('role_key', 'cash')->first()->current_balance)->toBe('0.0000');
});

it('makes posted entries immutable and reverses with a mirror entry', function () {
    $e = ($this->sale)();
    expect(fn () => $e->update(['description' => 'x']))->toThrow(LogicException::class);
    expect(fn () => $e->delete())->toThrow(LogicException::class);
    expect(fn () => $e->lines()->first()->update(['debit' => 1]))->toThrow(LogicException::class);
    $rev = $this->svc->reverse($e->fresh(), null, null, 'oops');
    expect($e->fresh()->status)->toBe('reversed')->and($rev->type)->toBe('reversal');
    expect(Account::where('role_key', 'cash')->first()->current_balance)->toBe('0.0000');
    expect($this->svc->reverse($e->fresh())->id)->toBe($rev->id); // repeat is a no-op
});

it('refuses posting into a closed period and allows after audited reopen', function () {
    $ps = app(PeriodService::class);
    $ps->createYear(2025);
    foreach (Period::whereYear('starts_on', 2025)->orderBy('starts_on')->limit(2)->get() as $p) {
        $ps->close($p, $this->owner->id);
    }
    expect(fn () => ($this->sale)(['entry_date' => '2025-02-10']))->toThrow(AccountingException::class);
    $ps->reopen(Period::where('starts_on', '2025-02-01')->first(), $this->owner->id, 'fix');
    expect(($this->sale)(['entry_date' => '2025-02-10'])->status)->toBe('posted');
});

it('keeps the ledger reports reconciled', function () {
    ($this->sale)();
    $this->svc->post(['entry_date' => now()->toDateString()], [['account' => 'cogs', 'debit' => '40'], ['account' => 'inventory', 'credit' => '40']]);
    $this->svc->post(['entry_date' => now()->toDateString()], [['account' => 'cash', 'debit' => '500'], ['account' => 'owner_capital', 'credit' => '500']]);
    $r = app(LedgerReports::class);
    expect($r->trialBalance()['balanced'])->toBeTrue();
    expect($r->profitLoss(null, null)['net_profit'])->toBe('60.0000');
    $bs = $r->balanceSheet();
    expect($bs['balanced'])->toBeTrue()->and($bs['current_earnings'])->toBe('60.0000');
    $gl = $r->generalLedger(Account::where('role_key', 'cash')->first());
    expect($gl['closing'])->toBe('600.0000');
});

it('serves the hub with cards and enforces permissions and refuses unknown accounts', function () {
    $this->actingAs($this->owner, 'admin');
    $t = '/admin';
    $this->get("$t/erp")->assertOk()->assertSee(route('admin.erp.accounts.index'));
    $this->get("$t/erp/accounts")->assertOk();
    $this->get("$t/erp/reports/trial-balance")->assertOk();
    $this->post("$t/erp/journals", ['entry_date' => now()->toDateString(), 'description' => 'm', 'type' => 'manual', 'post_now' => 1, 'lines' => [
        ['account_id' => Account::where('role_key', 'cash')->value('id'), 'debit' => 10], ['account_id' => Account::where('role_key', 'owner_capital')->value('id'), 'credit' => 10]]])->assertRedirect();
    expect(JournalEntry::first()->status)->toBe('posted');
    // an unknown account id is refused
    $foreign = 999999;
    $this->post("$t/erp/journals", ['entry_date' => now()->toDateString(), 'description' => 'm', 'type' => 'manual', 'lines' => [['account_id' => $foreign, 'debit' => 5], ['account_id' => $foreign, 'credit' => 5]]])->assertNotFound();
    // permission gate
    $this->owner->roles->each->revokePermissionTo('erp.journals.post');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->owner->unsetRelation('permissions')->unsetRelation('roles');
    $this->post("$t/erp/journals", ['entry_date' => now()->toDateString(), 'description' => 'm', 'type' => 'manual', 'post_now' => 1, 'lines' => [
        ['account_id' => Account::where('role_key', 'cash')->value('id'), 'debit' => 10], ['account_id' => Account::where('role_key', 'owner_capital')->value('id'), 'credit' => 10]]])->assertForbidden();
});
