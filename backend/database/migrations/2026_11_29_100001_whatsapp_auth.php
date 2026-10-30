<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Phone-based admin login, one super admin per scope, WhatsApp connections, DB-driven auth settings, OTP store. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('central_admins', function (Blueprint $t) {
            if (! Schema::hasColumn('central_admins', 'phone')) {
                $t->string('phone', 20)->nullable()->after('email'); // E.164 digits without '+'
            }
            $t->boolean('is_super_admin')->default(false)->after('is_active');
        });
        $this->unique('central_admins', 'central_admins_phone_unique', 'phone');
        // Generated column is 1 for the super admin and NULL otherwise; a UNIQUE index over it allows at most one row.
        DB::statement('ALTER TABLE central_admins ADD COLUMN super_admin_slot TINYINT GENERATED ALWAYS AS (IF(is_super_admin, 1, NULL)) STORED, ADD UNIQUE KEY central_admins_one_super (super_admin_slot)');

        Schema::table('tenant_admins', function (Blueprint $t) {
            if (! Schema::hasColumn('tenant_admins', 'phone')) {
                $t->string('phone', 20)->nullable()->after('email');
            }
            $t->boolean('is_super_admin')->default(false)->after('is_active');
        });
        $this->unique('tenant_admins', 'tenant_admins_tenant_id_phone_unique', 'tenant_id, phone');
        DB::statement('ALTER TABLE tenant_admins ADD COLUMN super_admin_slot TINYINT GENERATED ALWAYS AS (IF(is_super_admin, 1, NULL)) STORED, ADD UNIQUE KEY tenant_admins_one_super (tenant_id, super_admin_slot)');

        // One row per owner: tenant_id NULL = central. owner_key makes (central|tenant) unique despite NULLs.
        Schema::create('whatsapp_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('owner_key'); // tenant_id, or 0 for central: the models set it; UNIQUE allows one row per owner
            $t->string('session_id', 40)->unique();
            $t->boolean('is_enabled')->default(true);
            $t->string('status', 20)->default('disconnected'); // disconnected|connecting|qr|connected|logged_out|error
            $t->string('phone', 20)->nullable();
            $t->string('storage_ref')->nullable(); // reference to the encrypted session dir; credentials never stored here
            $t->timestamp('last_connected_at')->nullable();
            $t->timestamp('last_event_at')->nullable();
            $t->string('last_error', 500)->nullable();
            $t->timestamps();
            $t->unique('owner_key');
        });

        Schema::create('whatsapp_session_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('whatsapp_session_id')->constrained()->cascadeOnDelete();
            $t->string('level', 10)->default('info');
            $t->string('event', 40);
            $t->string('message', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['whatsapp_session_id', 'id']);
        });

        // Key/value runtime settings per owner (OTP length, expiry, cooldown, attempts, ...). Defaults are persisted on first read.
        Schema::create('auth_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('owner_key');
            $t->string('key', 60);
            $t->text('value');
            $t->timestamps();
            $t->unique(['owner_key', 'key']);
        });

        Schema::create('admin_otps', function (Blueprint $t) {
            $t->id();
            $t->string('guard', 10);
            $t->unsignedBigInteger('admin_id');
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('code_hash'); // never the plain code
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->unsignedSmallInteger('resend_count')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('last_sent_at');
            $t->timestamp('consumed_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['guard', 'admin_id', 'consumed_at']);
        });
    }

    /** Idempotent: a dev database may already carry a phone index from earlier experiments. */
    private function unique(string $table, string $name, string $cols): void
    {
        if (! DB::selectOne("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$name])) {
            DB::statement("ALTER TABLE `$table` ADD UNIQUE KEY `$name` ($cols)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_otps');
        Schema::dropIfExists('auth_settings');
        Schema::dropIfExists('whatsapp_session_logs');
        Schema::dropIfExists('whatsapp_sessions');
        DB::statement('ALTER TABLE tenant_admins DROP INDEX tenant_admins_one_super, DROP COLUMN super_admin_slot');
        Schema::table('tenant_admins', function (Blueprint $t) {
            $t->dropUnique('tenant_admins_tenant_id_phone_unique');
            $t->dropColumn(['phone', 'is_super_admin']);
        });
        DB::statement('ALTER TABLE central_admins DROP INDEX central_admins_one_super, DROP COLUMN super_admin_slot');
        Schema::table('central_admins', function (Blueprint $t) {
            $t->dropUnique('central_admins_phone_unique');
            $t->dropColumn(['phone', 'is_super_admin']);
        });
    }
};
