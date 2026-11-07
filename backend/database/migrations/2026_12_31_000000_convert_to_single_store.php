<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/*
 | Multi-tenant → single store (REMOVE_MULTI_TENANCY_PLAN.md §1.3–1.6, §1.12; data rules in docs/plans/single-store/INVENTORY.md §C).
 |
 |  1. Abort when `tenants` holds more than one row (restore a backup / remove the extra tenants first).
 |  2. Merge rows that only differed by owner in the nullable-tenant tables (auth_settings, whatsapp_*, erp_exchange_rates):
 |     WhatsApp session `central` (the paired device) and its conversations/settings win.
 |  3. Every `tenant_id` (and WhatsApp/auth `owner_key`) column: drop its FKs, recreate unique/plain indexes without it, drop it.
 |  4. Renames: tenant_admins→admins, ai_tenant_settings→ai_settings, erp_owners.tenant_admin_id→admin_id.
 |     Listings merge into the catalog (§1.13): tenant_products price / sale_price / cost / min_stock_alert_qty / content / gift_* → products,
 |     tenant_product_variants price / sale_price / cost / min_stock_alert_qty → product_variants; products.is_active = catalog AND listing
 |     active (no listing → inactive, price NULL); polymorphic rows pointing at TenantProduct(Variant) ids remapped to products / product_variants ids.
 |     The tenant row becomes key-value rows in `settings` (former settings JSON keys stay encrypted). tenants / tenant_products /
 |     tenant_product_variants are dropped.
 |  5. central_admins merged into admins by normalised phone (fallback e-mail), roles unioned, the central super admin keeps its OTP bypass
 |     (otp_exempt); every stored central-admin id is remapped; central_admins dropped.
 |  6. Class names in every *_type column, activity log names and guard columns rewritten; obsolete TenantNotification rows and
 |     queued jobs that serialised a tenant are deleted (QR codes: run `php artisan qr:rebuild` afterwards).
 |  7. Spatie: every role/permission on guard `admin`, same names merged, renames (ai_tenants.*→ai_platform.*, tenants.view/edit→
 |     general_settings.view/edit, erp_central.audit→erp.audit.view, tenant-owner→owner), then PermissionSeeder (super-admin/owner = all).
 |
 | MySQL only (the app and the test suite run on MySQL). Re-runnable: every step checks the current schema first.
 | down() is not supported: restore the pre-conversion dump instead.
 */
return new class extends Migration
{
    /** Plain class renames (same ids). */
    private const OLD = [
        'App\\Models\\TenantAdmin' => 'App\\Models\\Admin',
        'App\\Models\\AiTenantSetting' => 'App\\Models\\AiSetting',
    ];

    /** Listing classes → catalog classes; the id changes through the old listing table: [new class, old table, column holding the new id]. */
    private const LISTING = [
        'App\\Models\\TenantProduct' => ['App\\Models\\Product', 'tenant_products', 'product_id'],
        'App\\Models\\TenantProductVariant' => ['App\\Models\\ProductVariant', 'tenant_product_variants', 'product_variant_id'],
    ];

    private const CENTRAL_ADMIN = 'App\\Models\\CentralAdmin';

    private const ADMIN = 'App\\Models\\Admin';

    private const PERMISSION_RENAMES = [
        'ai_tenants.view' => 'ai_platform.view',
        'ai_tenants.edit' => 'ai_platform.edit',
        'tenants.view' => 'general_settings.view',
        'tenants.edit' => 'general_settings.edit',
        'erp_central.audit' => 'erp.audit.view',
    ];

    private const PERMISSION_DROPS = ['tenants.create', 'tenants.delete', 'erp_central.view', 'erp_central.reports'];

    private const ROLE_RENAMES = ['tenant-owner' => 'owner'];

    private const TABLE_RENAMES = [
        'tenant_admins' => 'admins',
        'ai_tenant_settings' => 'ai_settings',
    ];

    /** Columns of the tenant row that become plain settings (the `settings` JSON keys become encrypted settings). */
    private const PROFILE = ['name', 'country', 'currency_code', 'currency_symbol', 'default_locale', 'locales', 'timezone'];

    /** Session `central` = the paired WhatsApp device; its owner_key was 0. Updated in mergeWhatsapp() when another session is kept. */
    private int $keepOwner = 0;

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            throw new RuntimeException('convert_to_single_store supports MySQL/MariaDB only.');
        }
        $this->guardSingleTenant();

        $this->mergeAuthSettings();
        $this->mergeWhatsapp();
        $this->mergeExchangeRates();
        $this->rebuildProofGuard();

        foreach ($this->tablesWith('tenant_id') as $table) {
            $this->dropColumnEverywhere($table, 'tenant_id');
        }
        foreach ($this->tablesWith('owner_key') as $table) {
            $this->dropColumnEverywhere($table, 'owner_key');
        }

        $this->renameTables();
        $this->mergeListings();
        $this->tenantRowToSettings();

        $map = $this->mergeCentralAdmins();
        $this->rewriteClassNames($map);
        $this->rewriteGuardColumns($map);
        $this->mergeSpatie();

        Schema::dropIfExists('tenant_product_variants');
        Schema::dropIfExists('tenant_products');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('central_admins');

        (new PermissionSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if (class_exists(\App\Models\Setting::class)) {
            \App\Models\Setting::flush();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('The single-store conversion cannot be rolled back: restore storage/app/private/backups/pre-single-store-*.sql instead.');
    }

    // ---------------------------------------------------------------- 1. guard

    private function guardSingleTenant(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }
        $n = DB::table('tenants')->count();
        if ($n > 1) {
            throw new RuntimeException("Single-store conversion aborted: the `tenants` table has $n rows (soft-deleted included). "
                .'Exactly one store can be kept: delete the other tenants and their data (or restore a single-tenant backup), then migrate again.');
        }
    }

    // ---------------------------------------------------------------- 2. owner merges (nullable tenant_id tables)

    /** whatsapp.* keys: the kept WhatsApp owner's value wins; login.* / otp.* (identical today): the store (tenant) row wins. */
    private function mergeAuthSettings(): void
    {
        if (! Schema::hasTable('auth_settings') || ! Schema::hasColumn('auth_settings', 'owner_key')) {
            return;
        }
        $this->resolveKeepOwner();
        $rows = DB::table('auth_settings')->orderBy('id')->get()->groupBy('key');
        foreach ($rows as $key => $group) {
            if ($group->count() < 2) {
                continue;
            }
            $winner = str_starts_with((string) $key, 'whatsapp.')
                ? ($group->firstWhere('owner_key', $this->keepOwner) ?? $group->first())
                : ($group->first(fn ($r) => (int) $r->owner_key !== 0) ?? $group->first());
            DB::table('auth_settings')->where('key', $key)->where('id', '!=', $winner->id)->delete();
        }
    }

    private function resolveKeepOwner(): ?object
    {
        if (! Schema::hasTable('whatsapp_sessions') || ! Schema::hasColumn('whatsapp_sessions', 'owner_key')) {
            return null;
        }
        $keep = DB::table('whatsapp_sessions')->where('session_id', 'central')->first()
            ?? DB::table('whatsapp_sessions')->where('status', 'connected')->orderByDesc('last_connected_at')->first()
            ?? DB::table('whatsapp_sessions')->orderByDesc('last_connected_at')->orderBy('id')->first();
        if ($keep) {
            $this->keepOwner = (int) $keep->owner_key;
        }

        return $keep;
    }

    /** One session (the paired `central` one), conversations unioned by jid (the kept owner's row wins), duplicate message ids dropped. */
    private function mergeWhatsapp(): void
    {
        $keep = $this->resolveKeepOwner();
        if ($keep) {
            DB::table('whatsapp_sessions')->where('id', '!=', $keep->id)->delete();
        }
        if (! Schema::hasTable('whatsapp_conversations') || ! Schema::hasColumn('whatsapp_conversations', 'owner_key')) {
            return;
        }
        $dupJids = DB::table('whatsapp_conversations')->select('jid')->groupBy('jid')->havingRaw('COUNT(*) > 1')->pluck('jid');
        foreach ($dupJids as $jid) {
            $group = DB::table('whatsapp_conversations')->where('jid', $jid)->orderByDesc('last_message_at')->orderBy('id')->get();
            $winner = $group->firstWhere('owner_key', $this->keepOwner) ?? $group->first();
            foreach ($group->where('id', '!=', $winner->id) as $loser) {
                $have = DB::table('whatsapp_messages')->where('conversation_id', $winner->id)->whereNotNull('wa_message_id')->pluck('wa_message_id')->all();
                DB::table('whatsapp_messages')->where('conversation_id', $loser->id)->whereIn('wa_message_id', $have ?: ['__none__'])->delete();
                DB::table('whatsapp_messages')->where('conversation_id', $loser->id)->update(['conversation_id' => $winner->id]);
                DB::table('whatsapp_conversations')->where('id', $winner->id)->increment('unread_count', (int) $loser->unread_count);
                DB::table('whatsapp_conversations')->where('id', $loser->id)->delete();
            }
        }
        if (Schema::hasColumn('whatsapp_messages', 'owner_key')) {
            $dupIds = DB::table('whatsapp_messages')->whereNotNull('wa_message_id')->select('wa_message_id')->groupBy('wa_message_id')->havingRaw('COUNT(*) > 1')->pluck('wa_message_id');
            foreach ($dupIds as $waId) {
                $group = DB::table('whatsapp_messages')->where('wa_message_id', $waId)->orderBy('id')->get();
                $winner = $group->firstWhere('owner_key', $this->keepOwner) ?? $group->first();
                DB::table('whatsapp_messages')->where('wa_message_id', $waId)->where('id', '!=', $winner->id)->delete();
            }
        }
    }

    /** (tenant, from, to, date) → (from, to, date): a store-specific rate wins over the global (NULL) one, as ExchangeRates::find did. */
    private function mergeExchangeRates(): void
    {
        if (! Schema::hasTable('erp_exchange_rates') || ! Schema::hasColumn('erp_exchange_rates', 'tenant_id')) {
            return;
        }
        $dups = DB::table('erp_exchange_rates')->select('from_currency', 'to_currency', 'effective_date')
            ->groupBy('from_currency', 'to_currency', 'effective_date')->havingRaw('COUNT(*) > 1')->get();
        foreach ($dups as $d) {
            $group = DB::table('erp_exchange_rates')->where((array) $d)->orderByRaw('tenant_id IS NULL')->orderByDesc('id')->get();
            DB::table('erp_exchange_rates')->whereIn('id', $group->skip(1)->pluck('id'))->delete();
        }
    }

    /** orders.proof_guard was "{tenant_id}:{hash}" (generated): rebuild it as "{hash}" so the column no longer depends on tenant_id. */
    private function rebuildProofGuard(): void
    {
        if (! Schema::hasColumn('orders', 'proof_guard') || ! Schema::hasColumn('orders', 'tenant_id')) {
            return;
        }
        if ($this->indexExists('orders', 'orders_proof_guard_unique')) {
            DB::statement('ALTER TABLE orders DROP INDEX orders_proof_guard_unique');
        }
        DB::statement('ALTER TABLE orders DROP COLUMN proof_guard');
        DB::statement("ALTER TABLE orders ADD COLUMN proof_guard VARCHAR(100) GENERATED ALWAYS AS (
            IF(payment_proof_hash IS NOT NULL AND status <> 'cancelled' AND deleted_at IS NULL AND (proof_status IS NULL OR proof_status <> 'rejected'),
               payment_proof_hash, NULL)) VIRTUAL");
        DB::statement('ALTER TABLE orders ADD UNIQUE INDEX orders_proof_guard_unique (proof_guard)');
    }

    // ---------------------------------------------------------------- 3. drop tenant_id / owner_key with their keys

    /** @return list<string> */
    private function tablesWith(string $column): array
    {
        return array_map(fn ($r) => $r->t, DB::select(
            'SELECT TABLE_NAME AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = ? ORDER BY TABLE_NAME', [$column]));
    }

    /** @return array<string,array{unique:bool,cols:list<string>}> index name => definition (PRIMARY excluded) */
    private function indexes(string $table): array
    {
        $out = [];
        $rows = DB::select('SELECT INDEX_NAME AS n, NON_UNIQUE AS nu, COLUMN_NAME AS c, SUB_PART AS sp FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME <> \'PRIMARY\' ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$table]);
        foreach ($rows as $r) {
            $out[$r->n]['unique'] = ! (int) $r->nu;
            $out[$r->n]['cols'][] = $r->sp ? "{$r->c}({$r->sp})" : $r->c;
        }

        return $out;
    }

    private function indexExists(string $table, string $name): bool
    {
        return array_key_exists($name, $this->indexes($table));
    }

    /** @return list<object{name:string,col:string,ref_table:string,ref_col:string,on_delete:string,on_update:string}> */
    private function foreignKeys(string $table): array
    {
        return DB::select('SELECT k.CONSTRAINT_NAME AS name, k.COLUMN_NAME AS col, k.REFERENCED_TABLE_NAME AS ref_table, k.REFERENCED_COLUMN_NAME AS ref_col,
                r.DELETE_RULE AS on_delete, r.UPDATE_RULE AS on_update
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
            WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL', [$table]);
    }

    /**
     * Drop FKs on $column, replace every index containing it by the same index without it (unique stays unique; a plain index is
     * skipped when another index already starts with the remaining columns), then drop the column. Replacements are created
     * before the old indexes are dropped so FKs on the other columns always keep a supporting index.
     */
    private function dropColumnEverywhere(string $table, string $column): void
    {
        foreach ($this->foreignKeys($table) as $fk) {
            if ($fk->col === $column) {
                DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `{$fk->name}`");
            }
        }

        $all = $this->indexes($table);
        $old = array_filter($all, fn ($ix) => in_array($column, $ix['cols'], true));
        $keepers = array_diff_key($all, $old);
        $create = [];
        foreach ($old as $ix) {
            $rest = array_values(array_filter($ix['cols'], fn ($c) => $c !== $column));
            if (! $rest) {
                continue;
            }
            $have = array_merge(array_values($keepers), $create);
            $covered = $ix['unique']
                ? collect($have)->contains(fn ($k) => $k['unique'] && $k['cols'] === $rest)
                : collect($have)->contains(fn ($k) => array_slice($k['cols'], 0, count($rest)) === $rest);
            if (! $covered) {
                $create[] = ['unique' => $ix['unique'], 'cols' => $rest];
            }
        }
        // a unique on the same columns makes a plain twin redundant
        $create = array_values(array_filter($create, fn ($c) => $c['unique']
            || ! collect($create)->contains(fn ($o) => $o['unique'] && array_slice($o['cols'], 0, count($c['cols'])) === $c['cols'])));

        foreach ($create as $c) {
            $name = $this->indexName($table, $c['cols'], $c['unique']);
            $cols = implode(', ', array_map(fn ($col) => preg_replace('/^(\w+)(\(\d+\))?$/', '`$1`$2', $col), $c['cols']));
            DB::statement("ALTER TABLE `$table` ADD ".($c['unique'] ? 'UNIQUE ' : '')."INDEX `$name` ($cols)");
        }
        foreach (array_keys($old) as $name) {
            DB::statement("ALTER TABLE `$table` DROP INDEX `$name`");
        }
        DB::statement("ALTER TABLE `$table` DROP COLUMN `$column`");
    }

    private function indexName(string $table, array $cols, bool $unique): string
    {
        $cols = array_map(fn ($c) => preg_replace('/\(\d+\)$/', '', $c), $cols);
        $name = strtolower($table.'_'.implode('_', $cols).'_'.($unique ? 'unique' : 'index'));
        if (strlen($name) > 64) {
            $name = substr($table, 0, 40).'_'.substr(md5(implode(',', $cols)), 0, 12).'_'.($unique ? 'uq' : 'ix');
        }
        $i = 1;
        $base = $name;
        while ($this->indexExists($table, $name)) {
            $name = substr($base, 0, 60).'_'.$i++;
        }

        return $name;
    }

    // ---------------------------------------------------------------- 4. renames

    private function renameTables(): void
    {
        foreach (self::TABLE_RENAMES as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }
        $this->renameColumnWithFk('erp_owners', 'tenant_admin_id', 'admin_id');
        foreach (array_merge(array_values(self::TABLE_RENAMES), ['erp_owners', 'growth_approvals']) as $table) {
            if (Schema::hasTable($table)) {
                $this->cleanKeyNames($table);
            }
        }
    }

    private function renameColumnWithFk(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $from)) {
            return;
        }
        $fks = array_values(array_filter($this->foreignKeys($table), fn ($fk) => $fk->col === $from));
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `{$fk->name}`");
        }
        DB::statement("ALTER TABLE `$table` RENAME COLUMN `$from` TO `$to`");
        foreach ($fks as $fk) {
            $this->addFk($table, $to, $fk->ref_table, $fk->ref_col, $fk->on_delete, $fk->on_update);
        }
    }

    private function addFk(string $table, string $col, string $refTable, string $refCol, string $onDelete, string $onUpdate): void
    {
        $name = "{$table}_{$col}_foreign";
        DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` FOREIGN KEY (`$col`) REFERENCES `$refTable` (`$refCol`) ON DELETE $onDelete ON UPDATE $onUpdate");
    }

    /** Index / FK names that still say "tenant" (they follow the old table / column names) → the new names. */
    private function cleanKeyNames(string $table): void
    {
        $swap = fn (string $n) => str_replace(
            ['tenant_admins', 'ai_tenant_settings', 'tenant_admin_id'],
            ['admins', 'ai_settings', 'admin_id'], $n);
        foreach ($this->foreignKeys($table) as $fk) {
            if (str_contains($fk->name, 'tenant')) {
                DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `{$fk->name}`");
                $this->addFk($table, $fk->col, $fk->ref_table, $fk->ref_col, $fk->on_delete, $fk->on_update);
            }
        }
        foreach (array_keys($this->indexes($table)) as $name) {
            if (str_contains($name, 'tenant') && ($new = $swap($name)) !== $name && ! $this->indexExists($table, $new)) {
                DB::statement("ALTER TABLE `$table` RENAME INDEX `$name` TO `$new`");
            }
        }
    }

    /** Listing data onto products / product_variants (§1.13). The old tables stay until the polymorphic rows are remapped. */
    private function mergeListings(): void
    {
        if (! Schema::hasColumn('products', 'price')) {
            DB::statement('ALTER TABLE products
                ADD COLUMN price DECIMAL(14,2) NULL AFTER weight,
                ADD COLUMN sale_price DECIMAL(14,2) NULL AFTER price,
                ADD COLUMN cost DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER sale_price,
                ADD COLUMN min_stock_alert_qty INT UNSIGNED NULL AFTER cost,
                ADD COLUMN content JSON NULL AFTER min_stock_alert_qty,
                ADD COLUMN gift_suitable TINYINT(1) NOT NULL DEFAULT 0 AFTER content,
                ADD COLUMN gift_recipients JSON NULL AFTER gift_suitable,
                ADD COLUMN gift_occasions JSON NULL AFTER gift_recipients,
                ADD INDEX products_is_active_id_index (is_active, id),
                ADD INDEX products_gift_suitable_index (gift_suitable)');
        }
        if (! Schema::hasColumn('product_variants', 'price')) {
            DB::statement('ALTER TABLE product_variants
                ADD COLUMN price DECIMAL(14,2) NULL AFTER weight,
                ADD COLUMN sale_price DECIMAL(14,2) NULL AFTER price,
                ADD COLUMN cost DECIMAL(14,4) NULL AFTER sale_price,
                ADD COLUMN min_stock_alert_qty INT UNSIGNED NULL AFTER cost');
        }
        if (Schema::hasTable('tenant_products')) {
            DB::statement('UPDATE products p JOIN tenant_products tp ON tp.product_id = p.id SET
                p.price = tp.price, p.sale_price = tp.sale_price, p.cost = tp.cost, p.min_stock_alert_qty = tp.min_stock_alert_qty,
                p.content = tp.content, p.gift_suitable = tp.gift_suitable, p.gift_recipients = tp.gift_recipients, p.gift_occasions = tp.gift_occasions,
                p.is_active = (p.is_active AND tp.is_active)');
            DB::statement('UPDATE products p LEFT JOIN tenant_products tp ON tp.product_id = p.id SET p.is_active = 0, p.price = NULL, p.sale_price = NULL WHERE tp.id IS NULL');
        }
        if (Schema::hasTable('tenant_product_variants')) {
            DB::statement('UPDATE product_variants v JOIN tenant_product_variants tv ON tv.product_variant_id = v.id SET
                v.price = tv.price, v.sale_price = tv.sale_price, v.cost = tv.cost, v.min_stock_alert_qty = tv.min_stock_alert_qty,
                v.is_active = (v.is_active AND tv.is_active)');
        }
    }

    /** The single tenant row → `settings` (profile columns plain, every former settings-JSON key encrypted); defaults on an empty install. */
    private function tenantRowToSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function ($t) {
                $t->id();
                $t->string('key', 120)->unique();
                $t->longText('value')->nullable();
                $t->boolean('encrypted')->default(false);
                $t->timestamps();
            });
        }
        if (DB::table('settings')->exists()) {
            return; // already converted
        }
        $row = Schema::hasTable('tenants') ? DB::table('tenants')->orderBy('id')->first() : null;
        $put = fn (string $key, mixed $value, bool $secret) => DB::table('settings')->insert([
            'key' => $key, 'encrypted' => $secret, 'created_at' => now(), 'updated_at' => now(),
            'value' => $secret ? Crypt::encryptString(json_encode($value, JSON_UNESCAPED_UNICODE)) : json_encode($value, JSON_UNESCAPED_UNICODE),
        ]);
        if (! $row) {
            foreach (config('settings.defaults', []) as $k => $v) {
                $put($k, $v, false);
            }

            return;
        }
        foreach (self::PROFILE as $k) {
            $put($k, $k === 'locales' ? json_decode((string) $row->locales, true) : $row->{$k}, false);
        }
        if ($row->settings !== null && $row->settings !== '') {
            try {
                $bag = json_decode(Crypt::decryptString($row->settings), true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                throw new RuntimeException('Single-store conversion aborted: tenants.settings cannot be decrypted with the current APP_KEY ('.$e->getMessage().').');
            }
            foreach ((array) $bag as $k => $v) {
                $put((string) $k, $v, true);
            }
        }
    }

    // ---------------------------------------------------------------- 5. admins

    /** @return array<int,int> central_admins.id => admins.id */
    private function mergeCentralAdmins(): array
    {
        if (! Schema::hasTable('central_admins') || ! Schema::hasTable('admins')) {
            return [];
        }
        $map = [];
        $digits = fn (?string $p) => preg_replace('/\D+/', '', (string) $p);
        foreach (DB::table('central_admins')->orderBy('id')->get() as $c) {
            $phone = $digits($c->phone);
            $match = $phone !== ''
                ? DB::table('admins')->whereRaw("REGEXP_REPLACE(COALESCE(phone, ''), '[^0-9]', '') = ?", [$phone])->first()
                : null;
            $match ??= $c->email ? DB::table('admins')->where('email', $c->email)->first() : null;
            $superTaken = fn (?int $except) => DB::table('admins')->where('is_super_admin', true)->when($except, fn ($q) => $q->where('id', '!=', $except))->exists();
            // the central super admin logged in without OTP: the merged account keeps that (per-account otp_exempt)
            $otpExempt = (bool) $c->otp_exempt || (bool) $c->is_super_admin;

            if ($match) {
                $update = ['otp_exempt' => $otpExempt || (bool) $match->otp_exempt, 'updated_at' => now()];
                if ($c->is_super_admin && ! $match->is_super_admin && ! $superTaken($match->id)) {
                    $update['is_super_admin'] = true;
                }
                if (! $match->email && $c->email && ! DB::table('admins')->where('email', $c->email)->exists()) {
                    $update['email'] = $c->email;
                }
                DB::table('admins')->where('id', $match->id)->update($update);
                $map[(int) $c->id] = (int) $match->id;

                continue;
            }
            $map[(int) $c->id] = (int) DB::table('admins')->insertGetId([
                'name' => $c->name,
                'email' => $c->email && ! DB::table('admins')->where('email', $c->email)->exists() ? $c->email : null,
                'phone' => $c->phone, 'password' => $c->password, 'is_active' => $c->is_active, 'otp_exempt' => $otpExempt,
                'is_super_admin' => $c->is_super_admin && ! $superTaken(null), // one super-admin flag; extras keep the super-admin ROLE
                'remember_token' => null, 'created_at' => $c->created_at, 'updated_at' => now(),
            ]);
        }

        return $map;
    }

    // ---------------------------------------------------------------- 6. class names / guards / stale data

    /** @param array<int,int> $map central admin id => admin id */
    private function rewriteClassNames(array $map): void
    {
        $cols = DB::select("SELECT TABLE_NAME AS t, COLUMN_NAME AS c, IS_NULLABLE AS n FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME LIKE '%\\_type' AND DATA_TYPE IN ('varchar', 'char', 'text')");
        foreach ($cols as $col) {
            $t = $col->t;
            $c = $col->c;
            $idCol = substr($c, 0, -5).'_id';
            $hasId = Schema::hasColumn($t, $idCol);
            $pivot = in_array($t, ['model_has_roles', 'model_has_permissions'], true);

            if ($hasId && DB::table($t)->where($c, self::CENTRAL_ADMIN)->exists()) {
                foreach ($map as $from => $to) {
                    DB::update("UPDATE IGNORE `$t` SET `$idCol` = ?, `$c` = ? WHERE `$c` = ? AND `$idCol` = ?", [$to, self::ADMIN, self::CENTRAL_ADMIN, $from]);
                }
                // left over: duplicates of an already merged pivot row, or ids of deleted central admins
                $this->dropLeftovers($t, $c, $idCol, self::CENTRAL_ADMIN, $pivot || $col->n !== 'YES');
            }
            foreach (self::OLD as $from => $to) {
                DB::update("UPDATE IGNORE `$t` SET `$c` = ? WHERE `$c` = ?", [$to, $from]);
                if ($pivot) {
                    DB::table($t)->where($c, $from)->delete();
                }
            }
            foreach (self::LISTING as $from => [$to, $oldTable, $newId]) {
                if (! DB::table($t)->where($c, $from)->exists()) {
                    continue;
                }
                if ($hasId && Schema::hasTable($oldTable)) {
                    DB::update("UPDATE IGNORE `$t` x JOIN `$oldTable` o ON o.id = x.`$idCol` SET x.`$c` = ?, x.`$idCol` = o.`$newId` WHERE x.`$c` = ?", [$to, $from]);
                }
                $this->dropLeftovers($t, $c, $hasId ? $idCol : null, $from, $pivot || $col->n !== 'YES');
            }
            // the tenant row has no successor model (its values are in `settings`)
            if (DB::table($t)->where($c, 'App\\Models\\Tenant')->exists()) {
                $this->dropLeftovers($t, $c, $hasId ? $idCol : null, 'App\\Models\\Tenant', $pivot || $col->n !== 'YES');
            }
        }

        if (Schema::hasTable('activity_log')) {
            foreach (['tenant' => 'settings', 'tenantproduct' => 'product', 'tenantproductvariant' => 'productvariant',
                'aitenantsetting' => 'aisetting', 'tenantadmin' => 'admin', 'centraladmin' => 'admin'] as $from => $to) {
                DB::table('activity_log')->where('log_name', $from)->update(['log_name' => $to]);
            }
        }
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')->where('type', 'App\\Notifications\\TenantNotification')->delete();
        }
        // queued jobs serialised a tenant id / tenant models and cannot run any more (QR codes: `php artisan qr:rebuild` instead)
        if (Schema::hasTable('jobs')) {
            DB::table('jobs')->where(fn ($q) => $q->where('payload', 'like', '%tenantId%')->orWhere('payload', 'like', '%App\\\\\\\\Models\\\\\\\\Tenant%')
                ->orWhere('payload', 'like', '%CentralAdmin%'))->delete();
        }
    }

    /** Rows still pointing at a removed class: nulled (nullable morph) or deleted (pivot / NOT NULL morph). */
    private function dropLeftovers(string $t, string $c, ?string $idCol, string $class, bool $delete): void
    {
        $q = DB::table($t)->where($c, $class);
        if ($delete) {
            $q->delete();
        } else {
            $q->update($idCol ? [$c => null, $idCol => null] : [$c => null]);
        }
    }

    /** guard-ish columns ('tenant' | 'central') → 'admin'; central admin ids remapped first. */
    private function rewriteGuardColumns(array $map): void
    {
        foreach ([['admin_otps', 'guard', 'admin_id'], ['whatsapp_conversations', 'assigned_guard', 'assigned_admin_id'],
            ['whatsapp_messages', 'sent_by_guard', 'sent_by_admin_id']] as [$t, $g, $id]) {
            if (! Schema::hasTable($t) || ! Schema::hasColumn($t, $g)) {
                continue;
            }
            foreach ($map as $from => $to) {
                DB::table($t)->where($g, 'central')->where($id, $from)->update([$g => 'admin', $id => $to]);
            }
            if ($t === 'admin_otps') {
                DB::table($t)->where($g, 'central')->delete(); // OTP of a central admin that no longer exists
            }
            DB::table($t)->whereIn($g, ['tenant', 'central'])->update([$g => 'admin']);
        }
    }

    // ---------------------------------------------------------------- 7. spatie

    private function mergeSpatie(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        // permissions: drops, renames, then one row per name on guard `admin`
        $drop = DB::table('permissions')->whereIn('name', self::PERMISSION_DROPS)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $drop)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $drop)->delete();
        DB::table('permissions')->whereIn('id', $drop)->delete();
        $this->mergeByName('permissions', fn (string $n) => self::PERMISSION_RENAMES[$n] ?? $n,
            [['role_has_permissions', 'permission_id'], ['model_has_permissions', 'permission_id']]);

        // roles: tenant custom roles were stored as "t{tenantId}:{name}"; tenant-owner → owner
        $this->mergeByName('roles', function (string $n) {
            $n = preg_replace('/^t\d+:/', '', $n);

            return self::ROLE_RENAMES[$n] ?? $n;
        }, [['role_has_permissions', 'role_id'], ['model_has_roles', 'role_id']]);
    }

    /** @param list<array{0:string,1:string}> $refs pivot tables + column pointing at $table.id */
    private function mergeByName(string $table, callable $finalName, array $refs): void
    {
        $groups = DB::table($table)->orderBy('id')->get()->groupBy(fn ($r) => $finalName($r->name));
        foreach ($groups as $name => $rows) {
            $keep = $rows->firstWhere('guard_name', 'admin') ?? $rows->first();
            foreach ($rows->where('id', '!=', $keep->id) as $other) {
                foreach ($refs as [$pivot, $col]) {
                    DB::update("UPDATE IGNORE `$pivot` SET `$col` = ? WHERE `$col` = ?", [$keep->id, $other->id]);
                    DB::table($pivot)->where($col, $other->id)->delete(); // duplicates of rows the kept id already had
                }
                DB::table($table)->where('id', $other->id)->delete();
            }
            if ($keep->name !== $name || $keep->guard_name !== 'admin') {
                DB::table($table)->where('id', $keep->id)->update(['name' => $name, 'guard_name' => 'admin', 'updated_at' => now()]);
            }
        }
    }
};
