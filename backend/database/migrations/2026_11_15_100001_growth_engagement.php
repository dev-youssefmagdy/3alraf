<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('channel', 20); // whatsapp|facebook|instagram|review|site_search|email
            $t->string('external_id', 120);
            $t->string('thread_id', 120)->nullable();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->text('text_masked'); // PII masked before storage; the raw text is never stored
            $t->char('text_hash', 40); // sha1 of normalised masked text (dedupe / grouping)
            $t->string('language', 5)->nullable();
            $t->string('category', 20)->nullable();
            $t->string('intent', 24)->nullable();
            $t->string('urgency', 8)->nullable();
            $t->string('sentiment', 10)->nullable();
            $t->string('status', 12)->default('new'); // new|classified|escalated|replied|closed
            $t->boolean('consent')->default(false);
            $t->boolean('opted_out')->default(false);
            $t->string('escalation_reason', 20)->nullable();
            $t->text('suggested_reply')->nullable();
            $t->string('reply_mode', 12)->nullable(); // suggest|auto|human
            $t->timestamp('window_expires_at')->nullable();
            $t->timestamp('received_at');
            $t->timestamp('first_response_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'channel', 'external_id'], 'interactions_ext_unique');
            $t->index(['tenant_id', 'status', 'received_at']);
            $t->index(['tenant_id', 'product_id', 'category']);
            $t->index(['tenant_id', 'text_hash']);
        });
        Schema::create('improvement_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('topic', 20); // interaction category
            $t->string('rule', 30);
            $t->string('title', 200);
            $t->string('owner', 100)->nullable();
            $t->date('due_on')->nullable();
            $t->string('status', 10)->default('open'); // open|doing|done|dismissed
            $t->json('evidence')->nullable();
            $t->decimal('before_value', 12, 2)->nullable();
            $t->decimal('after_value', 12, 2)->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'product_id', 'topic']);
        });
        Schema::create('reputation_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('topic', 20)->nullable();
            $t->decimal('rate', 6, 2);
            $t->decimal('baseline_rate', 6, 2);
            $t->decimal('z_score', 7, 2);
            $t->unsignedInteger('sample');
            $t->boolean('pause_auto_reply')->default(true);
            $t->string('status', 10)->default('open'); // open|resolved
            $t->json('examples')->nullable(); // masked, grouped complaint texts
            $t->date('detected_on');
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });
        Schema::create('tracked_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('code', 12)->unique();
            $t->string('channel', 20)->nullable();
            $t->unsignedBigInteger('interaction_id')->nullable();
            $t->unsignedBigInteger('product_id');
            $t->string('utm_source', 40)->nullable();
            $t->string('utm_campaign', 60)->nullable();
            $t->unsignedInteger('clicks')->default(0);
            $t->timestamps();
            $t->index(['tenant_id', 'product_id']);
        });
        Schema::create('missed_opportunities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('rule', 30);
            $t->string('dedupe_key', 80);
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('title', 200);
            $t->json('evidence');
            $t->string('probable_cause', 300);
            $t->string('confidence', 8); // low|medium|high
            $t->string('suggestion', 300);
            $t->string('owner', 100)->nullable();
            $t->date('due_on')->nullable();
            $t->string('status', 10)->default('open'); // open|accepted|done|dismissed
            $t->string('result_note', 500)->nullable();
            $t->unsignedBigInteger('experiment_id')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'dedupe_key'], 'missed_opp_unique');
            $t->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['missed_opportunities', 'tracked_links', 'reputation_alerts', 'improvement_tasks', 'interactions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
