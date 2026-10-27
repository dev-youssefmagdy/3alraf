<?php

namespace App\Services\Growth;

use App\Models\IntegrationClient;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Issues / rotates / revokes integration credentials. Plain token + HMAC secret are returned ONCE (only a hash / ciphertext is stored). */
class IntegrationClientService
{
    public function __construct(private ActionLogger $log) {}

    /** @return array{0:IntegrationClient,1:string,2:string} client, plain token, plain hmac secret */
    public function create(string $name, array $scopes, ?string $webhookUrl, ?int $adminId): array
    {
        $scopes = $this->validScopes($scopes);
        $this->assertWebhook($webhookUrl);
        [$prefix, $token, $secret] = $this->credentials();
        $client = IntegrationClient::create(['name' => $name, 'token_prefix' => $prefix, 'token_hash' => hash('sha256', $token), 'hmac_secret' => $secret, 'scopes' => $scopes, 'webhook_url' => $webhookUrl ?: null]);
        $this->log->log('integration.client.created', 'admin', $adminId, null, $client, 'ok', ['scopes' => $scopes]);

        return [$client, $prefix.'.'.$token, $secret];
    }

    public function update(IntegrationClient $c, string $name, array $scopes, ?string $webhookUrl, ?int $adminId): IntegrationClient
    {
        $scopes = $this->validScopes($scopes);
        $this->assertWebhook($webhookUrl);
        $c->update(['name' => $name, 'scopes' => $scopes, 'webhook_url' => $webhookUrl ?: null]);
        $this->log->log('integration.client.updated', 'admin', $adminId, null, $c, 'ok', ['scopes' => $scopes]);

        return $c;
    }

    /** @return array{0:string,1:string} new plain token, new secret */
    public function rotate(IntegrationClient $c, ?int $adminId): array
    {
        if ($c->isRevoked()) {
            throw ValidationException::withMessages(['state' => __('growth_hub.clients.already_revoked')]);
        }
        [$prefix, $token, $secret] = $this->credentials();
        $c->update(['token_prefix' => $prefix, 'token_hash' => hash('sha256', $token), 'hmac_secret' => $secret]);
        $this->log->log('integration.client.rotated', 'admin', $adminId, null, $c);

        return [$prefix.'.'.$token, $secret];
    }

    public function revoke(IntegrationClient $c, ?int $adminId): void
    {
        $c->update(['revoked_at' => now()]);
        $this->log->log('integration.client.revoked', 'admin', $adminId, null, $c);
    }

    /** Resolve a "prefix.token" bearer to a live client (constant-time hash compare); null when unknown/revoked. */
    public function authenticate(string $bearer): ?IntegrationClient
    {
        if (! str_contains($bearer, '.')) {
            return null;
        }
        [$prefix, $token] = explode('.', $bearer, 2);
        $c = IntegrationClient::withoutGlobalScopes()->where('token_prefix', $prefix)->first();

        return $c && ! $c->isRevoked() && hash_equals($c->token_hash, hash('sha256', $token)) ? $c : null;
    }

    /** Only allow-listed scopes; any scope that smells like price/refund/stock/budget writes is refused. */
    public function validScopes(array $scopes): array
    {
        $scopes = array_values(array_unique(array_map('strval', $scopes)));
        foreach ($scopes as $s) {
            $bad = collect((array) config('growth.forbidden_scope_words'))->contains(fn ($w) => str_contains(strtolower($s), $w));
            if ($bad || ! array_key_exists($s, config('growth.scopes'))) {
                throw ValidationException::withMessages(['scopes' => __('growth_hub.clients.bad_scope', ['scope' => $s])]);
            }
        }
        if (! $scopes) {
            throw ValidationException::withMessages(['scopes' => __('growth_hub.clients.need_scope')]);
        }

        return $scopes;
    }

    private function assertWebhook(?string $url): void
    {
        if ($url && ! UrlGuard::isSafe($url)) {
            throw ValidationException::withMessages(['webhook_url' => __('growth_hub.clients.bad_webhook')]);
        }
    }

    private function credentials(): array
    {
        do {
            $prefix = Str::lower(Str::random(10));
        } while (IntegrationClient::withoutGlobalScopes()->where('token_prefix', $prefix)->exists());

        return [$prefix, Str::random(48), Str::random(48)];
    }
}
