<?php

use App\Models\Marketer;
use App\Support\Marketing\MarketingPermissions;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Product / variant images as `product_update` suggestions (POST products/{id}/image-suggestion), owner decision 2026-11-22:
 * - every EXISTING marketer account gets every marketer permission directly (review, approve and apply included), so the current marketing
 *   user can approve and apply image suggestions; accounts created later keep their role's permissions only;
 * - every non-revoked Marketing API token that can upload studio files (`media:write`) also gets `suggestions:write:product_update`.
 * Idempotent. down() leaves permissions and scopes in place (removing them could lock out accounts edited since).
 */
return new class extends Migration
{
    private const SCOPE = 'suggestions:write:product_update';

    public function up(): void
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('marketers')) {
            (new PermissionSeeder)->run();   // makes sure every marketer permission exists
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            Marketer::query()->each(fn (Marketer $m) => $m->givePermissionTo(MarketingPermissions::PANEL));
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if (Schema::hasTable('marketing_api_tokens')) {
            foreach (DB::table('marketing_api_tokens')->whereNull('revoked_at')->get(['id', 'scopes']) as $t) {
                $scopes = json_decode((string) $t->scopes, true) ?: [];
                if (in_array('media:write', $scopes, true) && ! in_array(self::SCOPE, $scopes, true)) {
                    DB::table('marketing_api_tokens')->where('id', $t->id)->update(['scopes' => json_encode([...$scopes, self::SCOPE])]);
                }
            }
        }
    }

    public function down(): void {}
};
