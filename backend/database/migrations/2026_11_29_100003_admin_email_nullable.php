<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Admins sign in with their phone now; e-mail becomes optional contact data (unique indexes still apply to non-null values). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['central_admins', 'tenant_admins'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('email')->nullable()->change());
        }
    }

    public function down(): void {}
};
