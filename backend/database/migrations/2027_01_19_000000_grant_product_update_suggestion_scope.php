<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grants the new `suggestions:write:product_update` scope to existing Marketing API tokens (the MCP server's token) that can already suggest
 * product changes (`suggestions:write:product`) and are not revoked. Read-only or narrower tokens are not widened. Idempotent.
 * down() removes the scope from every token (it did not exist before this release).
 */
return new class extends Migration
{
    private const SCOPE = 'suggestions:write:product_update';

    private const REQUIRES = 'suggestions:write:product';

    public function up(): void
    {
        foreach (DB::table('marketing_api_tokens')->whereNull('revoked_at')->get(['id', 'scopes']) as $t) {
            $scopes = json_decode((string) $t->scopes, true) ?: [];
            if (in_array(self::REQUIRES, $scopes, true) && ! in_array(self::SCOPE, $scopes, true)) {
                DB::table('marketing_api_tokens')->where('id', $t->id)->update(['scopes' => json_encode([...$scopes, self::SCOPE])]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('marketing_api_tokens')->get(['id', 'scopes']) as $t) {
            $scopes = json_decode((string) $t->scopes, true) ?: [];
            if (in_array(self::SCOPE, $scopes, true)) {
                DB::table('marketing_api_tokens')->where('id', $t->id)->update(['scopes' => json_encode(array_values(array_diff($scopes, [self::SCOPE])))]);
            }
        }
    }
};
