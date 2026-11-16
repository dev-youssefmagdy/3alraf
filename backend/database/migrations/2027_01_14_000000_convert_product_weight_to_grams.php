<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Product / variant weight: kilograms DECIMAL(10,3) -> whole grams (unsigned INT). Existing values are multiplied by 1000.
 * Widened first so ×1000 cannot overflow DECIMAL(10,3) during the conversion.
 */
return new class extends Migration
{
    private const TABLES = ['products', 'product_variants'];

    public function up(): void
    {
        foreach (self::TABLES as $t) {
            DB::statement("ALTER TABLE `$t` MODIFY `weight` DECIMAL(16,3) NULL");
            DB::table($t)->whereNotNull('weight')->update(['weight' => DB::raw('ROUND(`weight` * 1000)')]);
            DB::statement("ALTER TABLE `$t` MODIFY `weight` INT UNSIGNED NULL COMMENT 'grams'");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $t) {
            DB::statement("ALTER TABLE `$t` MODIFY `weight` DECIMAL(16,3) NULL");
            DB::table($t)->whereNotNull('weight')->update(['weight' => DB::raw('`weight` / 1000')]);
            DB::statement("ALTER TABLE `$t` MODIFY `weight` DECIMAL(10,3) NULL");
        }
    }
};
