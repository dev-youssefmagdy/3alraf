<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Per-admin "log in without OTP" switch (password only), set from the add/edit admin form by a system-role holder. */
    public function up(): void
    {
        foreach (['central_admins', 'tenant_admins'] as $t) {
            Schema::table($t, fn (Blueprint $b) => $b->boolean('otp_exempt')->default(false)->after('is_active'));
        }
    }

    public function down(): void
    {
        foreach (['central_admins', 'tenant_admins'] as $t) {
            Schema::table($t, fn (Blueprint $b) => $b->dropColumn('otp_exempt'));
        }
    }
};
