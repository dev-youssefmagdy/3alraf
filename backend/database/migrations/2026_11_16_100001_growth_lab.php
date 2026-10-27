<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_experiments', function (Blueprint $t) {
            $t->unsignedBigInteger('product_id')->nullable()->after('tenant_id'); // set = Marketing Experiment Lab experiment
            $t->string('audience', 200)->nullable();
            $t->string('platform', 30)->nullable();
            $t->text('angle')->nullable();
            $t->string('utm_campaign', 60)->nullable();
            $t->timestamp('content_approved_at')->nullable();
            $t->index(['tenant_id', 'product_id']);
        });
        Schema::table('tracked_links', function (Blueprint $t) {
            $t->unsignedBigInteger('growth_experiment_id')->nullable();
            $t->unsignedBigInteger('ambassador_id')->nullable();
            $t->index(['tenant_id', 'growth_experiment_id']);
            $t->index(['tenant_id', 'ambassador_id']);
        });
        Schema::create('experiment_ad_days', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('growth_experiment_id')->constrained()->cascadeOnDelete();
            $t->date('day');
            $t->string('creative_key', 80)->default('');
            $t->string('platform', 30)->nullable();
            $t->string('platform_campaign_id', 80)->nullable();
            $t->string('platform_ad_id', 80)->nullable();
            $t->decimal('spend', 14, 2)->default(0);
            $t->unsignedBigInteger('impressions')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->timestamps();
            $t->unique(['growth_experiment_id', 'day', 'creative_key'], 'exp_ad_days_unique');
            $t->index(['tenant_id', 'day']);
        });
        Schema::create('small_batch_tests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('code', 12)->unique();
            $t->string('name', 150);
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('mode', 14); // interest|survey|limited_stock
            $t->text('description')->nullable();
            $t->string('survey_question', 300)->nullable();
            $t->boolean('preorder')->default(false);
            $t->text('preorder_terms')->nullable(); // required (delivery window, refund policy) when preorder
            $t->unsignedInteger('limited_stock_qty')->nullable();
            $t->unsignedInteger('target_signups')->default(30);
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->string('status', 10)->default('draft'); // draft|running|closed
            $t->string('decision', 10)->nullable(); // reorder|hold|drop
            $t->string('decision_note', 300)->nullable();
            $t->timestamp('opened_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });
        Schema::create('small_batch_signups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('small_batch_test_id')->constrained()->cascadeOnDelete();
            $t->char('contact_hash', 40); // sha1(tenant|test|normalised contact): dedupe without storing the contact
            $t->string('contact_masked', 60);
            $t->string('answer', 300)->nullable();
            $t->unsignedInteger('quantity')->default(1);
            $t->boolean('preorder_accepted')->default(false);
            $t->boolean('consent')->default(false);
            $t->timestamps();
            $t->unique(['small_batch_test_id', 'contact_hash'], 'sb_signup_unique');
        });
        Schema::create('ambassadors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $t->string('status', 10)->default('active'); // active|paused|ended
            $t->string('disclosure', 300); // how the ambassador discloses the relationship (e.g. #ad / "I earn a commission")
            $t->decimal('reward_per_order', 10, 2)->default(0); // fixed reward per VALID order (on top of normal affiliate commission rules)
            $t->string('reward_rule', 300)->nullable();
            $t->timestamp('terms_accepted_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'affiliate_id']);
        });
        Schema::create('ambassador_ideas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('title', 150);
            $t->text('body');
            $t->string('channel', 20)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ambassador_ideas', 'ambassadors', 'small_batch_signups', 'small_batch_tests', 'experiment_ad_days'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('tracked_links', fn (Blueprint $t) => $t->dropColumn(['growth_experiment_id', 'ambassador_id']));
        Schema::table('growth_experiments', fn (Blueprint $t) => $t->dropColumn(['product_id', 'audience', 'platform', 'angle', 'utm_campaign', 'content_approved_at']));
    }
};
