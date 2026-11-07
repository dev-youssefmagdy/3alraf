<?php

// The single-store conversion migration (2026_12_31_000000_convert_to_single_store) on a small multi-tenant fixture.
// Runs on a throw-away database `<test db>_conv` (structure cloned from the migrated test DB + the legacy tenant tables) through
// separate connections, so the RefreshDatabase transaction of the test DB is never touched. The scratch DB is dropped afterwards.

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $base = config('database.connections.mysql');
    $this->src = $base['database'];
    $this->scratch = $this->src.'_conv';
    config(['database.connections.conv_root' => $base, 'database.connections.conv' => ['database' => $this->scratch] + $base]);
    $root = DB::connection('conv_root');
    $root->statement("DROP DATABASE IF EXISTS `{$this->scratch}`");
    $root->statement("CREATE DATABASE `{$this->scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach ($root->select("SHOW FULL TABLES FROM `{$this->src}` WHERE Table_type = 'BASE TABLE'") as $row) {
        $t = array_values((array) $row)[0];
        $root->statement("CREATE TABLE `{$this->scratch}`.`$t` LIKE `{$this->src}`.`$t`");
    }
    $this->db = DB::connection('conv');
    $this->legacyFixture = function () {
        $db = $this->db;
        $db->statement('CREATE TABLE tenants (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL, country VARCHAR(2) NULL,
            currency_code VARCHAR(3) NOT NULL, currency_symbol VARCHAR(10) NOT NULL, currency_rate DECIMAL(14,6) NOT NULL DEFAULT 1, default_locale VARCHAR(5) NOT NULL, locales JSON NULL,
            timezone VARCHAR(64) NOT NULL, settings TEXT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL)');
        $db->statement('CREATE TABLE tenant_products (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL,
            price DECIMAL(14,2) NOT NULL, sale_price DECIMAL(14,2) NULL, cost DECIMAL(14,4) NOT NULL DEFAULT 0, min_stock_alert_qty INT UNSIGNED NULL, content JSON NULL,
            gift_suitable TINYINT(1) NOT NULL DEFAULT 0, gift_recipients JSON NULL, gift_occasions JSON NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, UNIQUE KEY tp_tenant_product (tenant_id, product_id))');
        $db->statement('CREATE TABLE tenant_product_variants (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, tenant_product_id BIGINT UNSIGNED NOT NULL,
            product_variant_id BIGINT UNSIGNED NOT NULL, price DECIMAL(14,2) NULL, sale_price DECIMAL(14,2) NULL, cost DECIMAL(14,4) NULL, min_stock_alert_qty INT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)');
        $db->statement('CREATE TABLE central_admins (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, email VARCHAR(190) NULL, phone VARCHAR(30) NULL,
            password VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, otp_exempt TINYINT(1) NOT NULL DEFAULT 0, is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
            remember_token VARCHAR(100) NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)');
        // a tenant-scoped table as it was: tenant_id + unique(tenant_id, slug) instead of unique(slug)
        $db->statement('ALTER TABLE brands DROP INDEX brands_slug_unique, ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id, ADD UNIQUE INDEX brands_tenant_id_slug_unique (tenant_id, slug)');
    };
    $this->runConversion = function () {
        $prev = DB::getDefaultConnection();
        DB::setDefaultConnection('conv');
        Setting::flush();
        try {
            (include database_path('migrations/2026_12_31_000000_convert_to_single_store.php'))->up();
        } finally {
            DB::setDefaultConnection($prev);
            Setting::flush();
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    };
});

afterEach(function () {
    DB::connection('conv')->disconnect();
    DB::connection('conv_root')->statement("DROP DATABASE IF EXISTS `{$this->scratch}`");
    DB::purge('conv');
    DB::purge('conv_root');
});

it('aborts with a clear message when more than one tenant exists', function () {
    ($this->legacyFixture)();
    foreach (['egy', 'sa'] as $slug) {
        $this->db->table('tenants')->insert(['slug' => $slug, 'name' => $slug, 'currency_code' => 'EGP', 'currency_symbol' => 'EGP', 'default_locale' => 'ar', 'timezone' => 'Africa/Cairo']);
    }
    expect(fn () => ($this->runConversion)())->toThrow(RuntimeException::class, 'the `tenants` table has 2 rows');
    expect(Schema::connection('conv')->hasColumn('brands', 'tenant_id'))->toBeTrue(); // nothing changed
});

it('converts a one-tenant fixture: tenant_id gone, unique keys rebuilt, listing merged, settings copied, admins merged by phone, morphs and guards rewritten', function () {
    ($this->legacyFixture)();
    $db = $this->db;
    $now = now();
    $tid = $db->table('tenants')->insertGetId(['slug' => 'egy', 'name' => 'Egypt X', 'country' => 'EG', 'currency_code' => 'EGP', 'currency_symbol' => 'ج.م', 'default_locale' => 'ar',
        'locales' => json_encode(['ar', 'en']), 'timezone' => 'Africa/Cairo', 'settings' => Crypt::encryptString(json_encode(['cod_collect_shipping_upfront' => false, 'tax_rate' => 14]))]);
    $db->table('brands')->insert(['tenant_id' => $tid, 'name' => json_encode(['ar' => 'ب']), 'slug' => 'b1', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $p1 = $db->table('products')->insertGetId(['type' => 'simple', 'name' => json_encode(['ar' => 'أ']), 'slug' => 'listed', 'sku' => 'L1', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $p2 = $db->table('products')->insertGetId(['type' => 'simple', 'name' => json_encode(['ar' => 'ب']), 'slug' => 'unlisted', 'sku' => 'U1', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $tp = $db->table('tenant_products')->insertGetId(['tenant_id' => $tid, 'product_id' => $p1, 'price' => 100, 'sale_price' => 90, 'cost' => 60, 'gift_suitable' => 1, 'is_active' => 1]);
    // the same person as tenant admin and central super admin (no e-mail; matched by normalised phone) + one more central admin
    $tenantAdmin = $db->table('admins')->insertGetId(['name' => 'Owner', 'phone' => '201558099183', 'password' => 'x', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $cSuper = $db->table('central_admins')->insertGetId(['name' => 'Super', 'phone' => '+20 155 809 9183', 'password' => 'x', 'is_super_admin' => 1, 'created_at' => $now]);
    $cOther = $db->table('central_admins')->insertGetId(['name' => 'Ops', 'phone' => '201000000222', 'email' => 'ops@x.test', 'password' => 'x', 'created_at' => $now]);
    // spatie on the two old guards: central super-admin role, tenant custom role "t{id}:Packers" and tenant-owner
    $rCentral = $db->table('roles')->insertGetId(['name' => 'super-admin', 'guard_name' => 'central', 'created_at' => $now, 'updated_at' => $now]);
    $rPack = $db->table('roles')->insertGetId(['name' => "t{$tid}:Packers", 'guard_name' => 'tenant', 'created_at' => $now, 'updated_at' => $now]);
    $rOwner = $db->table('roles')->insertGetId(['name' => 'tenant-owner', 'guard_name' => 'tenant', 'created_at' => $now, 'updated_at' => $now]);
    $db->table('model_has_roles')->insert([
        ['role_id' => $rCentral, 'model_type' => 'App\\Models\\CentralAdmin', 'model_id' => $cSuper],
        ['role_id' => $rOwner, 'model_type' => 'App\\Models\\TenantAdmin', 'model_id' => $tenantAdmin],
        ['role_id' => $rPack, 'model_type' => 'App\\Models\\CentralAdmin', 'model_id' => $cOther],
    ]);
    $db->table('qr_codes')->insert(['owner_type' => 'App\\Models\\TenantProduct', 'owner_id' => $tp, 'url' => 'http://egy.x/ar/product/listed', 'url_hash' => sha1('a'), 'path' => 'tenants/1/qr/products/a.png', 'created_at' => $now, 'updated_at' => $now]);
    $db->table('activity_log')->insert(['log_name' => 'tenantproduct', 'description' => 'updated', 'subject_type' => 'App\\Models\\TenantProduct', 'subject_id' => $tp, 'causer_type' => 'App\\Models\\CentralAdmin', 'causer_id' => $cOther, 'created_at' => $now, 'updated_at' => $now]);

    ($this->runConversion)();

    $s = Schema::connection('conv');
    $cols = fn (string $c) => collect($db->select('SELECT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ?', [$this->scratch, $c]))->pluck('t')->all();
    expect($cols('tenant_id'))->toBe([])->and($cols('owner_key'))->toBe([])
        ->and($s->hasTable('tenants'))->toBeFalse()->and($s->hasTable('tenant_products'))->toBeFalse()->and($s->hasTable('tenant_product_variants'))->toBeFalse()->and($s->hasTable('central_admins'))->toBeFalse();
    // unique(tenant_id, slug) → unique(slug)
    $uniq = collect($s->getIndexes('brands'))->first(fn ($i) => $i['unique'] && $i['columns'] === ['slug']);
    expect($uniq)->not->toBeNull()->and(collect($s->getIndexes('brands'))->pluck('name')->filter(fn ($n) => str_contains($n, 'tenant'))->all())->toBe([]);
    // listing merged into products
    $a = $db->table('products')->find($p1);
    $b = $db->table('products')->find($p2);
    expect((string) $a->price)->toBe('100.00')->and((string) $a->sale_price)->toBe('90.00')->and((float) $a->cost)->toBe(60.0)->and((int) $a->is_active)->toBe(1)->and((int) $a->gift_suitable)->toBe(1)
        ->and($b->price)->toBeNull()->and((int) $b->is_active)->toBe(0);
    // tenant row → settings (profile plain, former settings JSON encrypted)
    $set = $db->table('settings')->get()->keyBy('key');
    expect(json_decode($set['name']->value))->toBe('Egypt X')->and(json_decode($set['currency_symbol']->value))->toBe('ج.م')->and(json_decode($set['locales']->value, true))->toBe(['ar', 'en'])
        ->and((bool) $set['cod_collect_shipping_upfront']->encrypted)->toBeTrue()->and(json_decode(Crypt::decryptString($set['cod_collect_shipping_upfront']->value)))->toBeFalse()
        ->and(json_decode(Crypt::decryptString($set['tax_rate']->value)))->toBe(14);
    // admins merged by phone: one account, super flag + OTP bypass kept; the other central admin becomes a new admin
    $admins = $db->table('admins')->orderBy('id')->get();
    $merged = $admins->firstWhere('id', $tenantAdmin);
    $ops = $admins->firstWhere('phone', '201000000222');
    expect($admins)->toHaveCount(2)->and((int) $merged->is_super_admin)->toBe(1)->and((int) $merged->otp_exempt)->toBe(1)
        ->and($ops)->not->toBeNull()->and($ops->email)->toBe('ops@x.test')->and((int) $ops->otp_exempt)->toBe(0);
    // morphs rewritten (ids remapped through the old tables), roles on guard admin, prefixes / tenant-owner renamed
    $mhr = $db->table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->get(['roles.name', 'roles.guard_name', 'model_type', 'model_id']);
    expect($mhr->pluck('model_type')->unique()->values()->all())->toBe(['App\\Models\\Admin'])
        ->and($mhr->pluck('guard_name')->unique()->values()->all())->toBe(['admin'])
        ->and($mhr->map(fn ($r) => $r->name.'@'.$r->model_id)->sort()->values()->all())->toBe(collect(['super-admin@'.$tenantAdmin, 'owner@'.$tenantAdmin, 'Packers@'.$ops->id])->sort()->values()->all());
    expect($db->table('roles')->whereIn('guard_name', ['central', 'tenant'])->count())->toBe(0)
        ->and($db->table('permissions')->where('guard_name', '!=', 'admin')->count())->toBe(0)->and($db->table('permissions')->count())->toBeGreaterThan(150);
    $qr = $db->table('qr_codes')->first();
    expect($qr->owner_type)->toBe('App\\Models\\Product')->and((int) $qr->owner_id)->toBe($p1);
    $log = $db->table('activity_log')->where('description', 'updated')->first();
    expect($log->log_name)->toBe('product')->and($log->subject_type)->toBe('App\\Models\\Product')->and((int) $log->subject_id)->toBe($p1)
        ->and($log->causer_type)->toBe('App\\Models\\Admin')->and((int) $log->causer_id)->toBe($ops->id);

    // re-running on a converted database is a no-op
    ($this->runConversion)();
    expect($db->table('admins')->count())->toBe(2)->and($db->table('settings')->count())->toBe($set->count())->and((string) $db->table('products')->find($p1)->price)->toBe('100.00');
});

it('fills the settings from config/settings.php on an empty install (no tenant row)', function () {
    ($this->runConversion)();
    $set = $this->db->table('settings')->pluck('value', 'key')->map(fn ($v) => json_decode($v, true));
    expect($set->keys()->sort()->values()->all())->toBe(collect(array_keys(config('settings.defaults')))->sort()->values()->all())
        ->and($set['currency_code'])->toBe(config('settings.defaults.currency_code'));
});
