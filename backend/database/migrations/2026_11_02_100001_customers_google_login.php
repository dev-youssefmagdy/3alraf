<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Continue with Google": customers.google_id (unique per tenant, tombstoned on soft delete), avatar, and a nullable password (Google-only accounts). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->string('google_id', 120)->nullable()->after('email');
            $t->string('avatar', 500)->nullable()->after('google_id');
            $t->string('password')->nullable()->change();
            $t->unique(['tenant_id', 'google_id']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->dropUnique(['tenant_id', 'google_id']);
            $t->dropColumn(['google_id', 'avatar']);
        });
        \DB::table('customers')->whereNull('password')->update(['password' => \Illuminate\Support\Str::random(60)]);
        Schema::table('customers', fn (Blueprint $t) => $t->string('password')->nullable(false)->change());
    }
};
