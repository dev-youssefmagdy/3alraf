<?php

use App\Models\CentralAdmin;
use App\Models\Erp\Account;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\PeriodService;
use App\Services\Erp\Reconciliation\LedgerVerifier;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    app(CoaTemplate::class)->seed($this->tenant);
    $this->owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->base = 'http://egy.3alraf.test/admin/erp';

    // ~2000 posted lines (1000 balanced two-line entries) written in bulk; cached balances kept consistent so erp:verify stays green
    $t = $this->tenant->id;
    $acc = fn (string $r) => Account::where('role_key', $r)->value('id');
    $ids = ['cash' => $acc('cash'), 'rev' => $acc('sales_revenue'), 'rent' => $acc('rent'), 'bank' => $acc('bank')];
    $period = app(PeriodService::class)->openPeriodFor(now());
    $entries = [];
    $lines = [];
    $today = now();
    for ($i = 1; $i <= 1000; $i++) {
        $d = $today->copy()->subDays($i % 90)->toDateString();
        if ($d < $period->starts_on->toDateString()) {
            $d = $period->starts_on->toDateString();
        }
        $entries[] = ['tenant_id' => $t, 'number' => 'JE-P'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'status' => 'posted', 'type' => 'auto', 'entry_date' => $d, 'period_id' => $period->id, 'currency' => 'EGP', 'exchange_rate' => 1,
            'source_type' => 'order', 'source_id' => $i, 'idempotency_key' => 'perf:'.$i, 'description' => 'perf', 'total' => '10.0000', 'created_at' => now(), 'updated_at' => now()];
    }
    DB::table('erp_journal_entries')->insert($entries);
    $eid = DB::table('erp_journal_entries')->where('tenant_id', $t)->orderBy('id')->pluck('id');
    foreach ($eid as $n => $id) {
        [$dr, $cr] = $n % 2 ? [$ids['rent'], $ids['cash']] : [$ids['cash'], $ids['rev']];
        $lines[] = ['tenant_id' => $t, 'entry_id' => $id, 'account_id' => $dr, 'debit' => '10.0000', 'credit' => '0.0000', 'created_at' => now(), 'updated_at' => now()];
        $lines[] = ['tenant_id' => $t, 'entry_id' => $id, 'account_id' => $cr, 'debit' => '0.0000', 'credit' => '10.0000', 'created_at' => now(), 'updated_at' => now()];
    }
    foreach (array_chunk($lines, 500) as $c) {
        DB::table('erp_journal_lines')->insert($c);
    }
    // 500 sales: cash +10 / revenue +10 ; 500 rent: rent +10 / cash -10  => cash 0, revenue 5000, rent 5000
    DB::table('erp_accounts')->where('id', $ids['cash'])->update(['current_balance' => '0.0000']);
    DB::table('erp_accounts')->where('id', $ids['rev'])->update(['current_balance' => '5000.0000']);
    DB::table('erp_accounts')->where('id', $ids['rent'])->update(['current_balance' => '5000.0000']);

    $this->measure = function (callable $fn, int $maxQueries, float $maxSeconds = 4.0) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $t0 = microtime(true);
        $fn();
        $secs = microtime(true) - $t0;
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();
        expect($n)->toBeLessThanOrEqual($maxQueries, "queries: $n")->and($secs)->toBeLessThan($maxSeconds, 'seconds: '.round($secs, 2));

        return $n;
    };
});

it('has the composite indexes the report, dashboard and central queries rely on', function () {
    $has = fn (string $table, array $cols) => collect(Schema::getIndexes($table))->contains(fn ($i) => array_slice($i['columns'], 0, count($cols)) === $cols);
    expect($has('erp_journal_lines', ['tenant_id', 'entry_id']))->toBeTrue()
        ->and($has('erp_journal_lines', ['tenant_id', 'account_id', 'branch_id']))->toBeTrue()
        ->and($has('erp_journal_lines', ['tenant_id', 'party_type', 'party_id', 'account_id']))->toBeTrue()
        ->and($has('erp_journal_entries', ['tenant_id', 'entry_date']))->toBeTrue()
        ->and($has('erp_journal_entries', ['tenant_id', 'source_type', 'source_id']))->toBeTrue()
        ->and($has('erp_journal_entries', ['tenant_id', 'idempotency_key']))->toBeTrue()
        ->and($has('erp_journal_entries', ['tenant_id', 'branch_id', 'entry_date']))->toBeTrue()
        ->and($has('erp_audit_logs', ['tenant_id', 'created_at']))->toBeTrue();
});

it('keeps the seeded ledger consistent (erp:verify green) and report pages bounded in queries and time', function () {
    expect(app(LedgerVerifier::class)->run($this->tenant)['ok'])->toBeTrue();
    $this->actingAs($this->owner, 'tenant');
    $to = now()->toDateString();
    $from = now()->subDays(120)->toDateString();
    foreach (['trial-balance' => 40, 'profit-loss' => 40, 'balance-sheet' => 40, 'general-ledger' => 45, 'cash-flow' => 45, 'journal-entries' => 40, 'receivables' => 60, 'payables' => 60, 'expenses' => 40,
        'sales' => 45, 'cogs' => 40, 'tax' => 45, 'owner-equity' => 50, 'inventory-valuation' => 60, 'reconciliation' => 120] as $rep => $max) {
        $url = "{$this->base}/reports/$rep?from=$from&to=$to".($rep === 'general-ledger' ? '&account_id='.Account::where('role_key', 'cash')->value('id') : '');
        ($this->measure)(fn () => $this->get($url)->assertOk(), $max, 6.0);
    }
    // the journal list page is paginated: 2000 lines never render at once
    $res = $this->get("{$this->base}/journals")->assertOk();
    expect(substr_count($res->getContent(), 'JE-P'))->toBeLessThanOrEqual(26);
    ($this->measure)(fn () => $this->get("{$this->base}/journals?page=3")->assertOk(), 30);
    ($this->measure)(fn () => $this->get("{$this->base}/accounts")->assertOk(), 30);
});

it('renders the dashboard, hub, branches and profit pages with a bounded number of queries (no N+1)', function () {
    $this->actingAs($this->owner, 'tenant');
    ($this->measure)(fn () => $this->get("{$this->base}/dashboard")->assertOk(), 90, 6.0);
    ($this->measure)(fn () => $this->get($this->base)->assertOk(), 70, 6.0);
    ($this->measure)(fn () => $this->get("{$this->base}/branches")->assertOk(), 70, 6.0);
    ($this->measure)(fn () => $this->get("{$this->base}/profits")->assertOk(), 90, 6.0);
    // query count must not grow with the data: add another 500 lines and compare
    $before = ($this->measure)(fn () => $this->get("{$this->base}/dashboard")->assertOk(), 90, 6.0);
    $t = $this->tenant->id;
    $e = DB::table('erp_journal_entries')->where('tenant_id', $t)->first();
    $ids = DB::table('erp_journal_entries')->where('tenant_id', $t)->limit(250)->pluck('id');
    foreach ($ids as $id) {
        $new = DB::table('erp_journal_entries')->insertGetId(['tenant_id' => $t, 'number' => 'JE-Q'.$id, 'status' => 'posted', 'type' => 'auto', 'entry_date' => $e->entry_date, 'period_id' => $e->period_id, 'currency' => 'EGP', 'exchange_rate' => 1,
            'idempotency_key' => 'perf2:'.$id, 'total' => '1.0000', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('erp_journal_lines')->insert([['tenant_id' => $t, 'entry_id' => $new, 'account_id' => Account::where('role_key', 'bank')->value('id'), 'debit' => '1.0000', 'credit' => '0.0000', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $t, 'entry_id' => $new, 'account_id' => Account::where('role_key', 'owner_capital')->value('id'), 'debit' => '0.0000', 'credit' => '1.0000', 'created_at' => now(), 'updated_at' => now()]]);
    }
    $after = ($this->measure)(fn () => $this->get("{$this->base}/dashboard")->assertOk(), 90, 6.0);
    expect($after)->toBe($before);
});

it('keeps the central consolidated pages bounded (GROUP BY tenant, no per-tenant loops) with a large ledger', function () {
    foreach (range(1, 6) as $i) {
        $x = Tenant::create(['slug' => "t$i", 'name' => "T$i", 'country' => 'XX', 'currency_code' => $i % 2 ? 'USD' : 'EGP', 'currency_symbol' => 'X', 'currency_rate' => 1,
            'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'UTC', 'is_active' => true]);
        app(CoaTemplate::class)->seed($x);
    }
    $this->actingAs(CentralAdmin::where('email', 'admin@3alraf.test')->first(), 'central');
    $c = 'http://3alraf.test/admin/erp';
    $q = [];
    foreach (['overview' => 40, 'performance' => 40, 'consolidated' => 40, 'transactions' => 30, 'parties' => 30, 'inventory' => 30, 'periods' => 50, 'anomalies' => 60, 'audit' => 20, 'migration' => 20] as $p => $max) {
        $q[$p] = ($this->measure)(fn () => $this->get("$c/$p")->assertOk(), $max, 6.0);
    }
    // adding tenants must not add queries (warm baselines: the first request also loads session / permission queries)
    $q['overview'] = ($this->measure)(fn () => $this->get("$c/overview")->assertOk(), 40, 6.0);
    $q['anomalies'] = ($this->measure)(fn () => $this->get("$c/anomalies")->assertOk(), 60, 6.0);
    foreach (range(7, 12) as $i) {
        $x = Tenant::create(['slug' => "t$i", 'name' => "T$i", 'country' => 'XX', 'currency_code' => 'EGP', 'currency_symbol' => 'X', 'currency_rate' => 1, 'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'UTC', 'is_active' => true]);
        app(CoaTemplate::class)->seed($x);
    }
    expect(($this->measure)(fn () => $this->get("$c/overview")->assertOk(), 40, 6.0))->toBe($q['overview']);
    expect(($this->measure)(fn () => $this->get("$c/anomalies")->assertOk(), 60, 6.0))->toBe($q['anomalies']);
});

it('caps the general ledger and the journal export at a safe maximum', function () {
    $this->actingAs($this->owner, 'tenant');
    $acc = Account::where('role_key', 'cash')->first();
    $gl = app(\App\Services\Erp\Reports\LedgerReports::class)->generalLedger($acc, null, null, null, 100);
    expect($gl['rows'])->toHaveCount(100)->and($gl['truncated'])->toBeTrue()->and($gl['closing'])->toBe('0.0000');
    $page = $this->get("{$this->base}/reports/general-ledger?account_id={$acc->id}&from=2000-01-01&to=".now()->toDateString())->assertOk();
    expect(substr_count($page->getContent(), 'JE-P'))->toBeLessThanOrEqual(5000);
});
