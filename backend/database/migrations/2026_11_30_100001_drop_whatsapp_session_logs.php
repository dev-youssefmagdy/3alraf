<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** The connection log was removed; databases migrated before that still carry the table. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('whatsapp_session_logs');
    }

    public function down(): void {}
};
