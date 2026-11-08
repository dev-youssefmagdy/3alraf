<?php

namespace App\Services\Marketing;

use App\DTOs\Marketing\MarketingTokenData;
use App\Models\MarketingApiToken;
use App\Repositories\Contracts\MarketingApiTokenRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issues / revokes / authenticates Marketing internal API service tokens (P4). Plain token "{prefix}.{secret}" is returned ONCE by create();
 * the database keeps only sha256(plain token). Authentication = prefix lookup + constant-time hash compare + revoke/expiry check.
 */
class MarketingTokenService
{
    public function __construct(private MarketingApiTokenRepositoryInterface $repo) {}

    public function findOrFail(int $id): MarketingApiToken
    {
        return $this->repo->findOrFail($id);
    }

    /** Scopes that may be granted now (config marketing.scopes with enabled=true). */
    public static function grantableScopes(): array
    {
        return array_keys(array_filter((array) config('marketing.scopes'), fn ($s) => (bool) ($s['enabled'] ?? false)));
    }

    /** @return array{0:MarketingApiToken,1:string} token row, plain token (show once) */
    public function create(MarketingTokenData $d): array
    {
        $bad = array_diff($d->scopes, self::grantableScopes());
        if ($bad || ! $d->scopes) {
            throw ValidationException::withMessages(['scopes' => __('marketing.tokens.bad_scope')]);
        }
        do {
            $prefix = config('marketing.token_prefix', 'mkt_').Str::lower(Str::random(12));
        } while ($this->repo->prefixExists($prefix));
        $plain = $prefix.'.'.Str::random(48);

        $token = $this->repo->create([
            'name' => $d->name, 'token_prefix' => $prefix, 'token_hash' => hash('sha256', $plain), 'scopes' => $d->scopes,
            'expires_at' => $d->expiresInDays ? now()->addDays($d->expiresInDays) : null, 'created_by' => $d->createdBy,
        ]);

        return [$token, $plain];
    }

    public function revoke(MarketingApiToken $t): void
    {
        if (! $t->isRevoked()) {
            $this->repo->update($t, ['revoked_at' => now()]);
        }
    }

    /** Resolve a bearer to a token row (constant-time compare); null when malformed / unknown / hash mismatch. Revoke/expiry checked by the caller. */
    public function resolve(string $bearer): ?MarketingApiToken
    {
        if (strlen($bearer) > 200 || ! str_contains($bearer, '.')) {
            return null;
        }
        [$prefix] = explode('.', $bearer, 2);
        $t = $prefix !== '' ? $this->repo->findByPrefix($prefix) : null;

        return $t && hash_equals((string) $t->token_hash, hash('sha256', $bearer)) ? $t : null;
    }

    public function touch(MarketingApiToken $t): void
    {
        if (! $t->last_used_at || $t->last_used_at->lt(now()->subMinute())) {
            $t->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }
}
