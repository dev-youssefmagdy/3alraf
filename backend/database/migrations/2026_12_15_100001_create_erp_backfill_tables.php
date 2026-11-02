<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_backfill_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->date('from_date');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->string('status', 12)->default('running'); // running|completed|failed|partial
            $t->boolean('dry_run')->default(false);
            $t->json('counts')->nullable();      // {type: {created, skipped, failed, amount}}
            $t->json('options')->nullable();
            $t->string('triggered_by', 80)->nullable(); // cli:<os user> | admin:<id>
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        // Per-order amounts already inside the opening balances (orders confirmed before the opening date), so later live postings only add the delta.
        Schema::create('erp_opening_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('order_id');
            $t->decimal('paid_amount', 14, 2)->default(0);
            $t->decimal('refunded_amount', 14, 2)->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'order_id']);
        });

        // Tenants that existed before the ERP shipped must not start auto-posting before their opening balances exist:
        // flag defaults to false for them (enabled by a completed `erp:backfill` or by an admin on the migration page). New tenants have no row = enabled.
        $now = now();
        DB::table('tenants')->orderBy('id')->each(function ($tenant) use ($now) {
            DB::table('erp_settings')->updateOrInsert(['tenant_id' => $tenant->id, 'key' => 'auto_posting_enabled'], ['value' => json_encode(false), 'created_at' => $now, 'updated_at' => $now]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_opening_orders');
        Schema::dropIfExists('erp_backfill_runs');
    }
};
