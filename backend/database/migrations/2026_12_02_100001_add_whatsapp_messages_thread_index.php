<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thread paging orders by id within a conversation (before/after cursors): (conversation_id, id) avoids a filesort. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', fn (Blueprint $t) => $t->index(['conversation_id', 'id'], 'wa_messages_conv_id_idx'));
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', fn (Blueprint $t) => $t->dropIndex('wa_messages_conv_id_idx'));
    }
};
