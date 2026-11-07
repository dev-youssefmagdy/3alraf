<?php

// Single store: ERP → Anomalies (was the central "audit" page, now inside the panel behind erp.audit.view).

use App\Models\Admin;
use App\Models\Erp\Account;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Erp\Audit\AnomalyService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    app(CoaTemplate::class)->seed();
    $this->je = app(JournalService::class);
    $this->today = now()->toDateString();
    $this->tx = fn (string $dr, string $cr, string $amt, ?string $date = null) => $this->je->post(['entry_date' => $date ?? $this->today, 'description' => 'tx'],
        [['account' => $dr, 'debit' => $amt], ['account' => $cr, 'credit' => $amt]]);
    $this->base = '/admin/erp';
});

it('a healthy ledger has no anomalies and the hub shows the empty alerts panel', function () {
    ($this->tx)('bank', 'owner_capital', '10000');
    ($this->tx)('receivable', 'sales_revenue', '500');
    expect(app(AnomalyService::class)->detect()->where('code', '!=', 'old_draft')->count())->toBe(0);
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');
    $this->get($this->base)->assertOk()->assertSee(__('erp_audit.alerts.title'))->assertSee(__('erp_audit.alerts.none'));
});

it('detects seeded problems, lists them by severity, filters and exports csv', function () {
    ($this->tx)('bank', 'owner_capital', '100');
    // unbalanced posted entry (bypasses the service on purpose)
    $bad = DB::table('erp_journal_entries')->insertGetId(['number' => 'JE-900001', 'status' => 'posted', 'type' => 'manual', 'entry_date' => $this->today, 'currency' => 'EGP', 'total' => 10, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('erp_journal_lines')->insert(['entry_id' => $bad, 'account_id' => Account::where('role_key', 'rent')->value('id'), 'debit' => 10, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()]);
    // stale draft, failed posting, reopened year
    $this->je->draft(['entry_date' => $this->today, 'description' => 'old'], [['account' => 'rent', 'debit' => '5'], ['account' => 'bank', 'credit' => '5']]);
    DB::table('erp_journal_entries')->where('status', 'draft')->update(['created_at' => now()->subDays(20)]);
    DB::table('erp_audit_logs')->insert([['action' => 'posting.failed', 'created_at' => now()], ['action' => 'year.reopened', 'created_at' => now()]]);
    // negative cash + control-account cache drift + a large manual entry
    ($this->tx)('rent', 'cash', '75');
    Account::query()->where('role_key', 'receivable')->update(['current_balance' => 9999]);
    ($this->tx)('receivable', 'sales_revenue', '250000');
    DB::table('erp_journal_entries')->where('total', '250000.0000')->update(['type' => 'manual']);

    $d = app(AnomalyService::class)->detect();
    expect($d->pluck('code')->all())->toContain('unbalanced_entry', 'old_draft', 'posting_failed', 'period_reopened', 'negative_cash', 'control_mismatch', 'large_manual')
        ->and($d->first()['severity'])->toBe('high')->and($d->firstWhere('code', 'unbalanced_entry')['entry_id'])->toBe($bad)
        ->and(app(AnomalyService::class)->count())->toBe((int) $d->sum('count'));

    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');
    $page = $this->get($this->base.'/anomalies')->assertOk()->assertSee(__('erp_audit.an.unbalanced_entry')[0]);
    expect($page->getContent())->toContain('data-code="negative_cash"')->toContain('data-code="large_manual"');
    $this->get($this->base.'/anomalies?severity=low')->assertOk()->assertSee('data-code="old_draft"', false)->assertDontSee('data-code="unbalanced_entry"', false);
    $this->get($this->base.'/anomalies?code=negative_cash')->assertOk()->assertSee('data-code="negative_cash"', false)->assertDontSee('data-code="large_manual"', false);
    $this->get($this->base.'/anomalies?severity=bogus')->assertRedirect();
    $this->get($this->base.'/anomalies?deep=1')->assertOk();
    $csv = $this->get($this->base.'/anomalies/export?code=unbalanced_entry')->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')->and($csv->streamedContent())->toContain('unbalanced_entry')->not->toContain('negative_cash');
    $this->get($this->base)->assertOk()->assertSee(__('erp_audit.an.unbalanced_entry')[0]); // hub alerts panel
});

it('needs erp.audit.view (page + export)', function () {
    $staff = Admin::query()->create(['name' => 'Acc', 'phone' => '201000000666', 'password' => 'password', 'is_active' => true]);
    $staff->givePermissionTo('erp.dashboard.view');
    $this->actingAs($staff, 'admin');
    $this->get($this->base.'/anomalies')->assertForbidden();
    $this->get($this->base.'/anomalies/export')->assertForbidden();
    $staff->givePermissionTo('erp.audit.view');
    $this->actingAs($staff->fresh(), 'admin')->get($this->base.'/anomalies')->assertOk();
});
