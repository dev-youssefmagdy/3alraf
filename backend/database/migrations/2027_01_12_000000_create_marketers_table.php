<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketer panel accounts (/marketer, guard `marketer`), separate from `admins`: own login (phone + password + WhatsApp OTP),
 * own roles/permissions (spatie guard `marketer`). ADMIN_REDESIGN_MARKETER_SPLIT_PLAN.md B1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('email')->nullable()->unique();
            $t->string('phone', 20)->unique();          // E.164 digits incl. country code (AdminPhone::digits)
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->boolean('otp_exempt')->default(false);
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketers');
    }
};
