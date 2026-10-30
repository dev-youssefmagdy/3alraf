<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** WhatsApp admin inbox. owner_key = tenant_id or 0 (central), set by the models (a generated column cannot sit under a cascading FK). */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropEmptyLegacyTables();

        Schema::create('whatsapp_conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('owner_key');
            $t->string('jid', 100);
            $t->string('phone', 20)->nullable();
            $t->string('name', 150)->nullable();
            $t->string('push_name', 150)->nullable();
            $t->string('avatar_path')->nullable();
            $t->unsignedBigInteger('last_message_id')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->string('last_message_preview', 200)->nullable();
            $t->unsignedInteger('unread_count')->default(0);
            $t->boolean('is_archived')->default(false);
            $t->boolean('is_pinned')->default(false);
            $t->unsignedBigInteger('assigned_admin_id')->nullable();
            $t->string('assigned_guard', 20)->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->timestamps();
            $t->unique(['owner_key', 'jid']);
            $t->index(['owner_key', 'last_message_at']);
            $t->index(['owner_key', 'phone']);
        });

        Schema::create('whatsapp_media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('owner_key');
            $t->string('disk', 30)->default('private');
            $t->string('path')->nullable();
            $t->string('mime', 120)->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->string('original_name')->nullable();
            $t->string('sha256', 64)->nullable();
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->unsignedInteger('duration')->nullable();
            $t->string('thumbnail_path')->nullable();
            $t->string('status', 12)->default('pending'); // pending|ready|failed|expired
            $t->timestamps();
            $t->index(['owner_key', 'status']);
        });

        Schema::create('whatsapp_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('owner_key');
            $t->string('wa_message_id', 100)->nullable(); // null while an outgoing message is still pending
            $t->string('direction', 3); // in|out
            $t->string('type', 20);
            $t->text('body')->nullable();
            $t->text('caption')->nullable();
            $t->json('payload')->nullable();
            $t->foreignId('reply_to_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $t->foreignId('media_id')->nullable()->constrained('whatsapp_media')->nullOnDelete();
            $t->string('status', 12)->default('pending'); // pending|sent|delivered|read|failed
            $t->string('error', 255)->nullable();
            $t->unsignedBigInteger('sent_by_admin_id')->nullable();
            $t->string('sent_by_guard', 20)->nullable();
            $t->timestamp('wa_timestamp')->nullable();
            $t->timestamp('edited_at')->nullable();
            $t->timestamp('deleted_at')->nullable(); // revoked for everyone (plain column, not SoftDeletes: the row stays as a tombstone)
            $t->timestamps();
            $t->unique(['owner_key', 'wa_message_id']);
            $t->index(['conversation_id', 'wa_timestamp', 'id']);
            $t->index(['owner_key', 'wa_timestamp']);
        });

        Schema::create('whatsapp_reactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->constrained('whatsapp_messages')->cascadeOnDelete();
            $t->string('reactor_jid', 100); // 'me' = the connected account
            $t->string('emoji', 32);
            $t->timestamps();
            $t->unique(['message_id', 'reactor_jid']);
        });
    }

    /** Dev DBs carry empty, migration-less leftovers of an older WhatsApp prototype under these names; drop them only when they hold no rows. */
    private function dropEmptyLegacyTables(): void
    {
        $legacy = ['whatsapp_message_attachments', 'whatsapp_reactions', 'whatsapp_messages', 'whatsapp_media', 'whatsapp_conversations'];
        foreach ($legacy as $t) {
            if (Schema::hasTable($t) && DB::table($t)->exists()) {
                throw new RuntimeException("Table $t already exists with data; refusing to replace it.");
            }
        }
        foreach ($legacy as $t) {
            Schema::dropIfExists($t);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_reactions');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_media');
        Schema::dropIfExists('whatsapp_conversations');
    }
};
