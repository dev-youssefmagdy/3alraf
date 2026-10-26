<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Services\Auth\SessionRevoker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Continue with Google" building blocks: HMAC-signed single-use tokens (state + handoff), the intended-URL sanitiser and the account
 * resolution (match by google_id, else link by VERIFIED e-mail, else create). Tokens are signed with a key derived from APP_KEY and a
 * purpose label, so a state can never be replayed as a handoff or vice versa.
 */
class GoogleAuthService
{
    public const STATE_TTL = 300;   // seconds a state is valid (tenant -> Google -> central)
    public const HANDOFF_TTL = 60;  // seconds a central -> tenant handoff is valid

    public function __construct(private SessionRevoker $sessions) {}

    public function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function callbackUrl(): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return $scheme.'://'.central_domain().($port ? ':'.$port : '').'/auth/google/callback';
    }

    // ---- signed tokens ----------------------------------------------------------------------------------------------------------

    public function sign(string $purpose, array $claims, int $ttl): string
    {
        $payload = $this->b64(json_encode($claims + ['p' => $purpose, 'exp' => now()->timestamp + $ttl, 'jti' => Str::random(24)]));

        return $payload.'.'.$this->mac($purpose, $payload);
    }

    /** @return array<string,mixed>|null null when malformed / tampered / expired / wrong purpose. Does NOT consume it. */
    public function verify(string $purpose, ?string $token): ?array
    {
        if (! is_string($token) || substr_count($token, '.') !== 1 || strlen($token) > 4096) {
            return null;
        }
        [$payload, $sig] = explode('.', $token);
        if (! hash_equals($this->mac($purpose, $payload), $sig)) {
            return null;
        }
        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        if (! is_array($data) || ($data['p'] ?? null) !== $purpose || ($data['exp'] ?? 0) < now()->timestamp || empty($data['jti'])) {
            return null;
        }

        return $data;
    }

    /** Atomic single use: true the first time a token id is seen. */
    public function consume(string $purpose, array $data): bool
    {
        return Cache::add('google-auth:'.$purpose.':'.$data['jti'], 1, self::STATE_TTL + 60);
    }

    private function mac(string $purpose, string $payload): string
    {
        return $this->b64(hash_hmac('sha256', $payload, hash_hmac('sha256', 'google-auth|'.$purpose, (string) config('app.key'), true), true));
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    // ---- open redirect guard ----------------------------------------------------------------------------------------------------

    /** Same-host relative path ("/ar/checkout?x=1") or null. Absolute URLs are only accepted when their host is $host. */
    public function safePath(?string $url, string $host): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || strlen($url) > 1000 || preg_match('/[\x00-\x1f\x7f\\\\]/', $url)) {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            $p = parse_url($url);
            if (! $p || strtolower($p['host'] ?? '') !== strtolower($host) || isset($p['user'])) {
                return null;
            }
            $url = ($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '');
        }
        if (! str_starts_with($url, '/') || str_starts_with($url, '//') || str_contains($url, '://')) {
            return null;
        }

        return $url;
    }

    // ---- accounts ---------------------------------------------------------------------------------------------------------------

    /**
     * Resolves (or creates) the customer of the CURRENT tenant for a verified Google identity.
     *
     * @param  array{sub:string,email:?string,email_verified:bool,name:?string,avatar:?string}  $g
     * @return array{0:?Customer,1:?string} [customer, error key under store.google.*]
     */
    public function resolve(array $g): array
    {
        $sub = (string) ($g['sub'] ?? '');
        if ($sub === '') {
            return [null, 'failed'];
        }
        $email = strtolower(trim((string) ($g['email'] ?? '')));

        $c = Customer::where('google_id', $sub)->first();
        if ($c) {
            return $c->is_active ? [$c, null] : [null, 'inactive'];
        }

        // Linking / creating trusts the e-mail only when Google vouches for it (else: account takeover via a spoofed address).
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || empty($g['email_verified'])) {
            return [null, 'unverified'];
        }

        // Soft-deleted rows carry the "#del{id}" tombstone, so they never equal a real address.
        $c = Customer::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($c) {
            if (! $c->is_active || str_ends_with((string) $c->getRawOriginal('email'), '@anonymized.invalid')) {
                return [null, 'inactive'];
            }
            if ($c->google_id !== null && $c->google_id !== $sub) {
                return [null, 'other_account'];
            }
            $wasUnverified = $c->email_verified_at === null;
            $c->forceFill(['google_id' => $sub, 'avatar' => $c->avatar ?: ($g['avatar'] ?? null), 'email_verified_at' => $c->email_verified_at ?? now()]);
            if ($wasUnverified) {
                // The address was never proven by whoever registered it (possible pre-registration by someone else): their password dies.
                $c->forceFill(['password' => null]);
            }
            $c->save();
            if ($wasUnverified) {
                $c->tokens()->delete();
                $this->sessions->revoke($c);
            }

            return [$c, null];
        }

        $c = new Customer(['name' => Str::limit(trim((string) ($g['name'] ?? '')) ?: Str::before($email, '@'), 120, ''), 'email' => $email, 'is_active' => true]);
        $c->forceFill(['google_id' => $sub, 'avatar' => $g['avatar'] ?? null, 'password' => null, 'email_verified_at' => now()])->save();

        return [$c, null];
    }
}
