<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** tenants.settings uses the `encrypted:array` cast (ciphertext is not valid JSON) -> needs a text column. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->longText('settings')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->json('settings')->nullable()->change());
    }
};
