<?php

use App\Http\Controllers\Admin\Erp\HubController;
use App\Services\Erp\Audit\AnomalyService;
use App\Services\Erp\Reports\ReportCatalog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Lang;

/** Every file that belongs to the ERP module (PHP + blade). */
function erpFiles(): array
{
    $dirs = [app_path('Services/Erp'), app_path('Http/Controllers/Admin/Erp'), app_path('Listeners/Erp'), app_path('Events/Erp'), app_path('Models/Erp'),
        resource_path('views/admin/erp')];
    $files = [app_path('Console/Commands/ErpBackfill.php'), app_path('Console/Commands/ErpDepreciate.php'), app_path('Console/Commands/ErpReconcile.php'), app_path('Console/Commands/ErpVerify.php'),
        app_path('Exceptions/AccountingException.php'), resource_path('views/components/layouts/admin.blade.php')];
    foreach ($dirs as $d) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS)) as $f) {
            if (preg_match('/\.php$/', $f->getFilename())) {
                $files[] = $f->getPathname();
            }
        }
    }

    return array_values(array_unique($files));
}

/** QA sign-off: erp* translation keys are also used outside the ERP folders (expenses, returns, POS, affiliate payouts, ErpRules, DecimalAmount, ...). */
function erpKeyFiles(): array
{
    $files = erpFiles();
    foreach ([app_path(), resource_path('views')] as $d) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS)) as $f) {
            if (preg_match('/\.php$/', $f->getFilename())) {
                $files[] = $f->getPathname();
            }
        }
    }

    return array_values(array_unique($files));
}

function erpGroups(): array
{
    return ['erp', 'erp_audit', 'erp_dist', 'erp_ext', 'erp_rep', 'erp_acc'];
}

function langFlat(string $group, string $locale): array
{
    $keys = [];
    $walk = function ($arr, $prefix) use (&$walk, &$keys) {
        foreach ($arr as $k => $v) {
            is_array($v) ? $walk($v, "$prefix$k.") : $keys[] = "$prefix$k";
        }
    };
    $walk(Lang::get($group, [], $locale), '');
    sort($keys);

    return $keys;
}

it('resolves every static erp* translation key used by ERP code in BOTH en and ar', function () {
    $missing = [];
    $dynamic = [];
    foreach (erpKeyFiles() as $file) {
        $src = file_get_contents($file);
        preg_match_all('/(?:__|trans|trans_choice|@lang)\(\s*[\'"](erp[a-z_]*\.[A-Za-z0-9_.\-]*)([\'"])(\s*\.)?/', $src, $m, PREG_SET_ORDER);
        foreach ($m as $x) {
            $key = $x[1];
            if (! empty($x[3]) || str_ends_with($key, '.')) {
                $dynamic[substr($key, 0, (int) strrpos($key, '.'))] = basename($file); // concatenated at runtime: the prefix must at least be an array in both locales

                continue;
            }
            foreach (['en', 'ar'] as $loc) {
                if (! Lang::has($key, $loc, false)) {
                    $missing[] = "$loc: $key (".basename($file).')';
                }
            }
        }
    }
    expect($missing)->toBe([]);
    foreach ($dynamic as $prefix => $file) {
        foreach (['en', 'ar'] as $loc) {
            expect(is_array(Lang::get($prefix, [], $loc)))->toBeTrue("$loc: dynamic group $prefix ($file) is not an array");
        }
    }
    expect(count($dynamic))->toBeGreaterThan(10);
});

it('keeps en and ar ERP language files in sync (same keys) and resolves every enumerated value', function () {
    foreach (erpGroups() as $g) {
        $en = langFlat($g, 'en');
        $ar = langFlat($g, 'ar');
        expect(array_values(array_diff($en, $ar)))->toBe([], "$g: keys missing in ar")->and(array_values(array_diff($ar, $en)))->toBe([], "$g: keys missing in en");
    }
    $need = [];
    foreach (HubController::CARDS as $c) {
        $need[] = 'erp.cards.'.$c[0];
    }
    foreach (array_keys(ReportCatalog::META) as $r) {
        $need[] = 'erp_rep.t.'.$r;
    }
    foreach (['draft', 'posted', 'reversed'] as $s) {
        $need[] = 'erp.statuses.'.$s;
    }
    foreach (\App\Models\Erp\Account::TYPES as $t) {
        $need[] = 'erp.types.'.$t;
    }
    foreach (['manual', 'auto', 'opening', 'adjusting', 'closing', 'reversal', 'credit_note', 'debit_note'] as $t) {
        $need[] = 'erp.entry_types.'.$t;
    }
    foreach (['high', 'medium', 'low'] as $s) {
        $need[] = 'erp_audit.severity.'.$s;
    }
    foreach ((new ReflectionClass(AnomalyService::class))->getConstant('SEVERITY') ?: [] as $code => $_) {
        $need[] = 'erp_audit.an.'.$code;
    }
    foreach (['cash', 'bank', 'pos_cash', 'gateway', 'wallet'] as $r) {
        $need[] = 'erp_ext.roles.'.$r;
    }
    $bad = [];
    foreach ($need as $k) {
        foreach (['en', 'ar'] as $loc) {
            Lang::has($k, $loc, false) || $bad[] = "$loc: $k";
        }
    }
    expect($bad)->toBe([]);
});

it('has no leftover debug code or env() calls in the ERP files', function () {
    $hits = [];
    foreach (erpFiles() as $f) {
        $src = file_get_contents($f);
        if (str_ends_with($f, '.blade.php')) {
            $src = preg_replace('/\{\{--.*?--\}\}/s', '', $src);
        }
        foreach (['/(?<![\w>:$])(dd|dump|var_dump|print_r|var_export|ray|error_log|debug_zval_refcount)\s*\(/', '/fwrite\(\s*STDERR/', '/console\.(log|debug)\(/', '/(?<![\w>:$])env\(/', '/@dd|@dump|TODO|FIXME|XXX/'] as $re) {
            if (preg_match($re, $src, $m)) {
                $hits[] = basename($f).': '.$m[0];
            }
        }
    }
    expect($hits)->toBe([]);
    // config:cache safety: no env() outside config/ anywhere in app/ or routes/
    $bad = [];
    foreach (['app', 'routes', 'database/seeders'] as $d) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($d), FilesystemIterator::SKIP_DOTS)) as $f) {
            if (str_ends_with($f->getFilename(), '.php') && preg_match('/(?<![\w>:$])env\(/', file_get_contents($f->getPathname()))) {
                $bad[] = $f->getPathname();
            }
        }
    }
    expect($bad)->toBe([]);
});

it('compiles every ERP blade view to valid PHP (view:cache safe)', function () {
    $bad = [];
    foreach (erpFiles() as $f) {
        if (! str_ends_with($f, '.blade.php')) {
            continue;
        }
        $php = Blade::compileString(file_get_contents($f));
        $tmp = tempnam(sys_get_temp_dir(), 'bld');
        file_put_contents($tmp, $php);
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($tmp).' 2>&1', $out, $code);
        unlink($tmp);
        $code === 0 || $bad[] = basename($f);
    }
    expect($bad)->toBe([]);
});

it('registers every ERP route, command and schedule entry', function () {
    expect(Artisan::call('route:list', ['--name' => 'erp.', '--json' => true]))->toBe(0);
    $routes = json_decode(Artisan::output(), true);
    expect(count($routes))->toBeGreaterThan(65);   // central ERP duplicates dropped (single store)
    $names = collect($routes)->pluck('name')->filter();
    expect($names->filter(fn ($n) => str_starts_with($n, 'admin.erp.'))->count())->toBeGreaterThan(60)->and($names->filter(fn ($n) => str_starts_with($n, 'central.') || str_starts_with($n, 'tenant.'))->count())->toBe(0);
    $cmds = array_keys(Artisan::all());
    foreach (['erp:depreciate', 'erp:backfill', 'erp:reconcile', 'erp:verify'] as $c) {
        expect($cmds)->toContain($c);
    }
    $sched = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command.' | '.$e->expression)->implode("\n");
    expect($sched)->toContain('erp:depreciate')->and($sched)->toContain('erp:verify');
    $dep = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'erp:depreciate'));
    expect($dep->expression)->toBe('20 3 1 * *');
});

it('keeps ERP permissions complete: every permission used by a gate exists in config/permissions.php', function () {
    $cfg = config('permissions');
    $flat = [];
    $walk = function ($a) use (&$walk, &$flat) {
        foreach ($a as $module => $acts) {
            if (is_array($acts) && Arr::isAssoc($acts)) {
                $walk($acts);
            } elseif (is_array($acts)) {
                foreach ($acts as $act) {
                    $flat[] = "$module.$act";
                }
            }
        }
    };
    $walk($cfg);
    $used = [];
    foreach (erpFiles() as $f) {
        preg_match_all('/(?:allow|can)\(\s*[\'"](erp\.[a-z_.]+)[\'"]/', file_get_contents($f), $m);
        $used = array_merge($used, $m[1]);
    }
    foreach (array_column(HubController::CARDS, 3) as $p) {
        array_push($used, ...explode('|', $p)); // "a|b" = any of the permissions (ERP-028 view-or-manage cards)
    }
    // abilities defined as Gates (not stored permissions) must really be defined: erp.access = "any ERP permission" (ERP-028)
    $gates = array_values(array_filter(array_unique($used), fn ($p) => \Illuminate\Support\Facades\Gate::has($p)));
    expect($gates)->toBe(['erp.access']);
    $unknown = array_values(array_diff(array_unique($used), $flat, $gates, ['expenses.view']));
    expect($unknown)->toBe([]);
});
