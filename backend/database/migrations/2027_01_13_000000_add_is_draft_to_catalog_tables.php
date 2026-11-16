<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog drafts: a record saved before all its data is known. A draft is always inactive (never on the storefront),
 * only its name is required, and it becomes a normal record once it is saved with full validation ("publish").
 */
return new class extends Migration
{
    private const TABLES = ['products', 'categories', 'brands', 'tags', 'attributes'];

    public function up(): void
    {
        foreach (self::TABLES as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->boolean('is_draft')->default(false)->index()->after('id'));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropIndex(['is_draft']);
                $table->dropColumn('is_draft');
            });
        }
    }
};
