<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $t) {
            $t->id();
            $t->string('code', 2)->unique();
            $t->json('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::table('cities', function (Blueprint $t) {
            $t->foreignId('country_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $t->json('state')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $t) {
            $t->dropConstrainedForeignId('country_id');
            $t->dropColumn('state');
        });
        Schema::dropIfExists('countries');
    }
};
