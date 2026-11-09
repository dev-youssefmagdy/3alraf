<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Replaces the single `suggestions:write` token scope with one scope per suggestion type, so existing tokens keep exactly the access they had. */
return new class extends Migration
{
    private const OLD = 'suggestions:write';

    private const TYPES = ['product', 'bundle', 'slider', 'offer', 'coupon', 'seo', 'blog', 'seasonal_theme', 'social_content', 'media_asset'];

    public function up(): void
    {
        $this->rewrite(function (array $scopes): array {
            if (! in_array(self::OLD, $scopes, true)) {
                return $scopes;
            }

            return array_values(array_unique([...array_diff($scopes, [self::OLD]), ...$this->perType()]));
        });
    }

    /** Only a token holding all ten scopes gets the old one back; a partial token must not be widened. */
    public function down(): void
    {
        $this->rewrite(function (array $scopes): array {
            $kept = array_values(array_diff($scopes, $this->perType()));

            return array_diff($this->perType(), $scopes) === [] ? [...$kept, self::OLD] : $kept;
        });
    }

    private function perType(): array
    {
        return array_map(fn (string $t) => 'suggestions:write:'.$t, self::TYPES);
    }

    private function rewrite(callable $change): void
    {
        foreach (DB::table('marketing_api_tokens')->get(['id', 'scopes']) as $token) {
            $before = json_decode($token->scopes, true) ?: [];
            $after = $change($before);
            if ($after !== $before) {
                DB::table('marketing_api_tokens')->where('id', $token->id)->update(['scopes' => json_encode($after)]);
            }
        }
    }
};
