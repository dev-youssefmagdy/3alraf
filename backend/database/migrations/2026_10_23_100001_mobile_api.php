<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->timestamp('email_verified_at')->nullable()->after('phone'));

        // One-time codes for the mobile app: password reset + e-mail verification (hashed, expiring, attempt-limited; per tenant because customer e-mails are only unique per tenant).
        Schema::create('customer_auth_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->string('type', 20); // reset | verify
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['tenant_id', 'email', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_auth_codes');
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('email_verified_at'));
    }
};
