<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attributes and attribute values are soft-deleted: removed (soft-deleted) variants keep their value links, so stock / purchase
 * history still shows "Black / 42 mm". The attribute code is tombstoned on delete (FreesUniqueOnDelete), so it can be reused.
 * `retention:purge` hard-deletes them once nothing references them any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['attributes', 'attribute_values'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->softDeletes()->index());
        }
    }

    public function down(): void
    {
        foreach (['attributes', 'attribute_values'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            });
        }
    }
};
