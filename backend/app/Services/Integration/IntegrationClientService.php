<?php

namespace App\Services\Integration;

use App\Models\IntegrationClient;
use App\Repositories\Contracts\IntegrationClientRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issues / rotates / revokes integration credentials. Plain token + HMAC secret are returned ONCE (only a hash / ciphertext is stored).
 * Changes are audited by the IntegrationClient model (activity log, secrets excluded).
 */
class IntegrationClientService
{
    public function __construct(private IntegrationClientRepositoryInterface $repo) {}

    public function findOrFail(int $id): IntegrationClient
    {
        return $this->repo->findOrFail($id);
    }

    /** @return array{0:IntegrationClient,1:string,2:string} client, plain token, plain hmac secret */
    public function create(string $name, array $scopes): array
    {
        $scopes = $this->validScopes($scopes);
        [$prefix, $token, $secret] = $this->credentials();
        $client = $this->repo->create(['name' => $name, 'token_prefix' => $prefix, 'token_hash' => hash('sha256', $token), 'hmac_secret' => $secret, 'scopes' => $scopes]);

        return [$client, $prefix.'.'.$token, $secret];
    }

    public function update(IntegrationClient $c, string $name, array $scopes): IntegrationClient
    {
        return $this->repo->update($c, ['name' => $name, 'scopes' => $this->validScopes($scopes)]);
    }

    /** @return array{0:string,1:string} new plain token, new secret */
    public function rotate(IntegrationClient $c): array
    {
        if ($c->isRevoked()) {
            throw ValidationException::withMessages(['state' => __('admin.integration.already_revoked')]);
        }
        [$prefix, $token, $secret] = $this->credentials();
        $this->repo->update($c, ['token_prefix' => $prefix, 'token_hash' => hash('sha256', $token), 'hmac_secret' => $secret]);

        return [$prefix.'.'.$token, $secret];
    }

    public function revoke(IntegrationClient $c): void
    {
        $this->repo->update($c, ['revoked_at' => now()]);
    }

    /** Resolve a "prefix.token" bearer to a live client (constant-time hash compare); null when unknown/revoked. */
    public function authenticate(string $bearer): ?IntegrationClient
    {
        if (! str_contains($bearer, '.')) {
            return null;
        }
        [$prefix, $token] = explode('.', $bearer, 2);
        $c = $this->repo->findByPrefix($prefix);

        return $c && ! $c->isRevoked() && hash_equals($c->token_hash, hash('sha256', $token)) ? $c : null;
    }

    /** Only the read scopes of config/integration.php exist (the API has no write endpoint at all). */
    public function validScopes(array $scopes): array
    {
        $scopes = array_values(array_unique(array_map('strval', $scopes)));
        foreach ($scopes as $s) {
            if (! array_key_exists($s, (array) config('integration.scopes'))) {
                throw ValidationException::withMessages(['scopes' => __('admin.integration.bad_scope', ['scope' => $s])]);
            }
        }
        if (! $scopes) {
            throw ValidationException::withMessages(['scopes' => __('admin.integration.need_scope')]);
        }

        return $scopes;
    }

    private function credentials(): array
    {
        do {
            $prefix = Str::lower(Str::random(10));
        } while ($this->repo->prefixExists($prefix));

        return [$prefix, Str::random(48), Str::random(48)];
    }
}
