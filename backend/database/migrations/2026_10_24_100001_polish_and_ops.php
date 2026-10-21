<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->string('slug', 150)->unique();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('product_tag', function (Blueprint $t) {
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $t->primary(['product_id', 'tag_id']);
        });

        // Searchable per-locale name columns (STORED: InnoDB FULLTEXT cannot index virtual columns).
        DB::statement("ALTER TABLE products ADD COLUMN name_ar VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(name, '$.ar'))) STORED NULL");
        DB::statement("ALTER TABLE products ADD COLUMN name_en VARCHAR(255) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) STORED NULL");
        DB::statement('ALTER TABLE products ADD FULLTEXT products_name_ar_ft (name_ar)');
        DB::statement('ALTER TABLE products ADD FULLTEXT products_name_en_ft (name_en)');

        foreach (['orders', 'customers', 'discount_codes'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
        }
        Schema::table('orders', function (Blueprint $t) {
            $t->timestamp('carrier_settled_at')->nullable()->after('delivered_at');
            $t->index(['tenant_id', 'shipping_company_id', 'carrier_settled_at'], 'orders_carrier_settle');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex('orders_carrier_settle');
            $t->dropColumn('carrier_settled_at');
        });
        foreach (['orders', 'customers', 'discount_codes'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
        DB::statement('ALTER TABLE products DROP INDEX products_name_ar_ft, DROP INDEX products_name_en_ft, DROP COLUMN name_ar, DROP COLUMN name_en');
        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('tags');
    }
};
