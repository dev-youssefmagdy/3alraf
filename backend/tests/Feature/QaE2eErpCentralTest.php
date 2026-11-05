<?php

/*
 * QA E2E — central ERP panel (read-only consolidated monitoring), en + ar, empty and populated platform.
 * Run alone: php artisan test tests/Feature/QaE2eErpCentralTest.php
 */

use App\Models\CentralAdmin;
use App\Models\Erp\JournalEntry;
use App\Models\Tenant;
use App\Services\Erp\Accounting\CoaTemplate;
use App\Services\Erp\Accounting\JournalService;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->egy = Tenant::where('slug', 'egy')->first();
    $this->c = 'http://'.central_domain().'/admin';
    $this->ce = $this->c.'/erp';
    $this->super = CentralAdmin::where('email', 'admin@3alraf.test')->first();
    $this->xss = '<script>alert("qa")</script><img src=x onerror=alert(1)>';
});

function qacData(): array
{
    $t = test();
    $usa = Tenant::create(['slug' => 'usa', 'name' => 'USA Store', 'country' => 'US', 'currency_code' => 'USD', 'currency_symbol' => '$', 'currency_rate' => 1,
        'default_locale' => 'en', 'locales' => ['en'], 'timezone' => 'UTC', 'is_active' => true]);
    $ids = [];
    foreach ([$t->egy, $usa] as $tn) {
        app(CoaTemplate::class)->seed($tn);
        $ids[$tn->slug] = TenantContext::run($tn, function () use ($t) {
            $je = app(JournalService::class);
            $je->post(['entry_date' => now()->toDateString(), 'description' => 'capital'], [['account' => 'bank', 'debit' => '5000'], ['account' => 'owner_capital', 'credit' => '5000']]);
            $je->post(['entry_date' => now()->toDateString(), 'description' => 'sale'], [['account' => 'receivable', 'debit' => '700'], ['account' => 'sales_revenue', 'credit' => '700']]);
            $x = $je->post(['entry_date' => now()->toDateString(), 'description' => 'XSS '.$t->xss], [['account' => 'rent', 'debit' => '100', 'memo' => $t->xss], ['account' => 'bank', 'credit' => '100']]);
            $je->draft(['entry_date' => now()->toDateString(), 'description' => 'draft'], [['account' => 'rent', 'debit' => '1'], ['account' => 'bank', 'credit' => '1']]);

            return $x->id;
        });
    }
    app()->forgetInstance('currentTenant');

    return ['usa' => $usa, 'entry' => $ids['egy']];
}

function qacUris(array $ids): array
{
    $out = [];
    foreach (Route::getRoutes()->getRoutes() as $r) {
        $n = (string) $r->getName();
        if (str_starts_with($n, 'central.erp.') && in_array('GET', $r->methods(), true)) {
            $out[$n] = '/'.str_replace('{id}', (string) ($n === 'central.erp.tenant' ? $ids['tenant'] : $ids['entry']), $r->uri());
        }
    }

    return $out;
}

function qacCheck(string $base, array $uris, bool $expectRtl, array $allow404 = []): array
{
    $bad = [];
    foreach ($uris as $name => $uri) {
        $res = test()->get('http://'.central_domain().$uri);
        $code = $res->getStatusCode();
        if ($code !== 200 && ! ($code === 404 && in_array($name, $allow404, true))) {
            $bad[] = "$name $uri => $code ".substr((string) ($res->exception?->getMessage() ?? ''), 0, 200);

            continue;
        }
        $ct = (string) $res->headers->get('Content-Type');
        if ($code === 200 && str_contains($ct, 'html')) {
            $html = $res->getContent();
            if ($raw = qaRawKeysC($html)) {
                $bad[] = "$name RAW KEYS ".implode(',', array_slice($raw, 0, 6));
            }
            if ($expectRtl && ! str_contains($html, 'dir="rtl"')) {
                $bad[] = "$name no rtl";
            }
            if (str_contains($html, '<script>alert("qa")') || str_contains($html, '<img src=x onerror')) {
                $bad[] = "$name XSS unescaped";
            }
        } elseif ($code === 200 && ! str_contains($ct, 'csv')) {
            $bad[] = "$name unexpected content-type $ct";
        }
    }

    return $bad;
}

function qaRawKeysC(string $html): array
{
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
    preg_match_all('/(?<![\w.\/:-])(?:erp|erp_central|erp_dist|erp_ext|erp_rep|admin)\.[a-z0-9_]+(?:\.[a-z0-9_\-]+)*\.?(?![\w(])/', $html, $m);

    return array_values(array_unique(array_filter($m[0], fn ($k) => ! preg_match('/^admin\.(3alraf|test|loc)/', $k))));
}

it('SMOKE central: every central ERP GET page/export renders for the super admin with multi-tenant data, en and ar, escaped', function () {
    $d = qacData();
    $this->actingAs($this->super, 'central');
    $uris = qacUris(['tenant' => $this->egy->id, 'entry' => $d['entry']]);
    expect(count($uris))->toBe(15);
    $this->get($this->c.'/locale/en');
    $bad = qacCheck($this->ce, $uris, false);
    $this->get($this->c.'/locale/ar')->assertRedirect();
    $bad = array_merge($bad, qacCheck($this->ce, $uris, true));
    expect($bad)->toBe([]);
});

it('SMOKE central: every page renders on a platform where NO tenant has ERP data yet (no CoA, no journals)', function () {
    $this->actingAs($this->super, 'central');
    expect(JournalEntry::withoutGlobalScopes()->count())->toBe(0);
    $bad = qacCheck($this->ce, qacUris(['tenant' => $this->egy->id, 'entry' => 1]), false, ['central.erp.transactions.show']);
    expect($bad)->toBe([]);
});

it('CENTRAL filters with odd inputs never 500 (from>to, invalid dates, huge ranges, non-numeric / unknown ids, unknown currency)', function () {
    $d = qacData();
    $this->actingAs($this->super, 'central');
    $bad = [];
    $qs = ['/overview?from=2026-12-31&to=2026-01-01', '/overview?from=1900-01-01&to=2999-12-31', '/overview?sort=nope&dir=asc&q='.urlencode('%_\'"'), '/overview?currency=ZZZ&period=open&anomalies=yes',
        '/overview/export?from=2026-12-31&to=2026-01-01', '/tenants/'.$this->egy->id.'?from=2026-12-31&to=2026-01-01', '/tenants/999999', '/performance?convert_to=XYZ', '/performance?convert_to=USD',
        '/consolidated?convert_to=EGP&from=1900-01-01', '/transactions?tenant_id=abc&branch_id=abc&account=1110&status=posted&q=%25', '/transactions?tenant_id=999999', '/transactions?page=99999',
        '/transactions/999999', '/parties?tenant_id='.$d['usa']->id.'&ageing=1', '/parties?tenant_id=abc', '/periods?tenant_id=999999', '/anomalies?deep=1&severity=high', '/anomalies?tenant_id=abc',
        '/anomalies/export?deep=1', '/audit?tenant_id=abc&user_id=abc&from=2026-12-31&to=2026-01-01', '/audit?action='.urlencode('<x>'), '/migration?status=failed&tenant_id=abc', '/transactions?q[]=x', '/audit?action[]=x'];
    foreach ($qs as $q) {
        $code = $this->get($this->ce.$q)->getStatusCode();
        if ($code >= 500) {
            $bad[] = "$q => $code";
        }
    }
    expect($bad)->toBe([]);
    $this->get($this->ce.'/tenants/999999')->assertNotFound();
    $this->get($this->ce.'/transactions/999999')->assertNotFound();
});

it('CENTRAL exports: overview & anomalies CSV are text/csv attachments with a header row', function () {
    qacData();
    $this->actingAs($this->super, 'central');
    foreach (['/overview/export', '/anomalies/export'] as $p) {
        $res = $this->get($this->ce.$p)->assertOk();
        $body = $res->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse ? $res->streamedContent() : $res->getContent();
        expect((string) $res->headers->get('Content-Type'))->toContain('text/csv')->and((string) $res->headers->get('Content-Disposition'))->toContain('attachment')->and($body)->toContain('tenant');
    }
    $csv = $this->get($this->ce.'/overview/export')->streamedContent();
    expect($csv)->toContain('USA Store');
});

it('CENTRAL consolidated/performance never sum across currencies; drill-down shows only that tenant', function () {
    $d = qacData();
    $this->actingAs($this->super, 'central');
    $this->get($this->c.'/locale/en');
    $html = $this->get($this->ce.'/consolidated')->assertOk()->getContent();
    expect($html)->toContain('EGP')->toContain('USD');
    $t = $this->get($this->ce.'/tenants/'.$d['usa']->id)->assertOk()->getContent();
    expect($t)->toContain('USA Store');
    $e = $this->get($this->ce.'/transactions/'.$d['entry'])->assertOk()->getContent();
    expect($e)->toContain('100.00');
});

it('PERMS central admin with erp_central.view only: hub opens (with an empty-state message), every other page 403, sidebar item present', function () {
    $role = Role::create(['name' => 'qa-erp-view', 'guard_name' => 'central']);
    $role->givePermissionTo('erp_central.view');
    $u = CentralAdmin::create(['name' => 'Viewer', 'email' => 'qa-viewer@3alraf.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($u->fresh(), 'central');
    $this->get($this->c.'/locale/en');
    $hub = $this->get($this->ce)->assertOk()->getContent();
    expect($hub)->toContain(__('erp_central.menu'))->toMatch('#<a href="http://'.preg_quote(central_domain()).'/admin/erp" class="side-link[^"]*\bactive\b#');
    foreach (qacUris(['tenant' => $this->egy->id, 'entry' => 1]) as $name => $uri) {
        if ($name !== 'central.erp.hub') {
            expect($this->get('http://'.central_domain().$uri)->getStatusCode())->toBe(403, $name);
        }
    }
    // the hub must not render cards for pages that would 403
    preg_match_all('#<a href="([^"]+/admin/erp/[^"]+)"#', $hub, $m);
    expect($m[1])->toBe([]);
});

it('QA-BUG-014 central hub for an erp_central.view-only admin explains why it is empty (no blank page with zero cards)', function () {
    $role = Role::create(['name' => 'qa-erp-view2', 'guard_name' => 'central']);
    $role->givePermissionTo('erp_central.view');
    $u = CentralAdmin::create(['name' => 'Viewer2', 'email' => 'qa-viewer2@3alraf.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($u->fresh(), 'central');
    $this->get($this->c.'/locale/en');
    $html = $this->get($this->ce)->assertOk()->getContent();
    preg_match('#<main\b.*?</main>#is', $html, $main);
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($main[0] ?? $html)));
    expect($text)->toMatch('/(no (cards|reports|access)|permission|not allowed|لا توجد|صلاحية)/i');
});

it('PANEL central sidebar ERP item for the super admin and every hub card opens', function () {
    qacData();
    $this->actingAs($this->super, 'central');
    $hub = $this->get($this->ce)->assertOk()->getContent();
    expect($hub)->toMatch('#<a href="http://'.preg_quote(central_domain()).'/admin/erp" class="side-link[^"]*\bactive\b#');
    preg_match_all('#data-card="([a-z_]+)"#', $hub, $cards);
    expect(count(array_unique($cards[1])))->toBe(count(\App\Http\Controllers\Central\Erp\HubController::CARDS));
    preg_match_all('#href="(http://'.preg_quote(central_domain()).'/admin/erp/[a-z\-]+)"#', $hub, $links);
    foreach (array_unique($links[1]) as $l) {
        $this->get($l)->assertOk();
    }
});
