<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** n8n Growth system, sprint N0: integration clients, event outbox, idempotent inbound, action log, approvals, experiments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('token_prefix', 12)->unique();
            $t->string('token_hash', 64);
            $t->text('hmac_secret'); // encrypted cast
            $t->json('scopes');
            $t->string('webhook_url', 500)->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'revoked_at']);
        });

        Schema::create('growth_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->uuid('event_id')->unique();
            $t->string('type', 60);
            $t->json('payload');
            $t->string('status', 12)->default('pending'); // pending|delivered|dead
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('next_attempt_at')->nullable();
            $t->json('delivered_to')->nullable();
            $t->string('last_error', 300)->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'next_attempt_at']);
            $t->index(['tenant_id', 'type', 'created_at']);
        });

        Schema::create('integration_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('integration_client_id')->constrained()->cascadeOnDelete();
            $t->string('idempotency_key', 120);
            $t->string('request_hash', 64);
            $t->string('method', 8);
            $t->string('path', 160);
            $t->unsignedSmallInteger('status_code')->nullable(); // null = in flight
            $t->json('response')->nullable();
            $t->timestamps();
            $t->unique(['integration_client_id', 'idempotency_key'], 'integration_requests_client_key_unique');
            $t->index('created_at');
        });

        Schema::create('growth_action_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('action', 80);
            $t->string('source', 20); // admin|n8n|system
            $t->unsignedBigInteger('actor_id')->nullable(); // tenant admin id or integration client id (by source)
            $t->string('reason', 500)->nullable();
            $t->string('outcome', 20)->default('ok'); // ok|rejected|failed
            $t->nullableMorphs('subject');
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'created_at']);
            $t->index(['tenant_id', 'action']);
        });

        Schema::create('growth_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('type', 60);
            $t->nullableMorphs('subject');
            $t->string('title', 200)->nullable();
            $t->json('payload')->nullable();
            $t->string('status', 12)->default('pending'); // pending|approved|rejected|expired
            $t->string('requested_by', 60)->nullable(); // "admin:3" | "client:2" | "system"
            $t->foreignId('decided_by')->nullable()->constrained('tenant_admins')->nullOnDelete();
            $t->string('reason', 500)->nullable();
            $t->json('result')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('growth_experiments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->text('hypothesis')->nullable();
            $t->string('objective', 300)->nullable();
            $t->string('metric', 100)->nullable();
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->decimal('budget_cap', 14, 2)->nullable();
            $t->string('status', 12)->default('draft'); // draft|running|paused|completed|cancelled
            $t->string('outcome', 12)->nullable(); // win|loss|inconclusive
            $t->text('learnings')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('growth_experiment_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('growth_experiment_id')->constrained()->cascadeOnDelete();
            $t->date('recorded_on');
            $t->string('metric', 100);
            $t->decimal('value', 18, 4)->nullable();
            $t->decimal('spend', 14, 2)->nullable();
            $t->string('note', 300)->nullable();
            $t->string('source', 20)->default('admin');
            $t->timestamps();
            $t->index(['growth_experiment_id', 'recorded_on'], 'growth_exp_results_exp_date_idx');
        });
    }

    public function down(): void
    {
        foreach (['growth_experiment_results', 'growth_experiments', 'growth_approvals', 'growth_action_log', 'integration_requests', 'growth_events', 'integration_clients'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
