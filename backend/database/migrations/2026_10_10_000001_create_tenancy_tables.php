<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();
            $t->string('name');
            $t->string('native_name');
            $t->string('direction', 3)->default('ltr');
            $t->boolean('is_active')->default(true);
            $t->boolean('is_default')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('country', 2)->nullable();
            $t->string('currency_code', 3)->default('USD');
            $t->string('currency_symbol', 10)->default('$');
            $t->decimal('currency_rate', 14, 6)->default(1); // to base currency
            $t->string('default_locale', 10)->default('ar');
            $t->json('locales');
            $t->string('timezone')->default('UTC');
            $t->boolean('is_active')->default(true);
            $t->json('settings')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('central_admins', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('tenant_admins', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
            $t->unique(['tenant_id', 'email']);
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('email');
            $t->string('phone')->nullable();
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
            $t->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        foreach (['customers', 'tenant_admins', 'central_admins', 'tenants', 'languages'] as $n) {
            Schema::dropIfExists($n);
        }
    }
};
