<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;

/**
 * Runtime auth/WhatsApp settings, stored in the auth_settings table (one namespace per owner: tenant id, or null = central).
 * The defaults below are only the initial values persisted on first read; nothing is read from .env or config files.
 */
final class AuthSettings
{
    public const DEFAULTS = [
        'whatsapp.enabled' => true,
        'otp.length' => 6,
        'otp.ttl_seconds' => 300,
        'otp.resend_cooldown_seconds' => 60,
        'otp.max_attempts' => 5,
        'otp.max_resends' => 3,
        'otp.message' => 'Your verification code is {code}. It expires in {minutes} minutes. Never share it with anyone.',
        'login.max_failures' => 5,
        'login.lockout_seconds' => 900,
    ];

    private array $cache = [];

    private function __construct(private readonly ?int $tenantId) {}

    public static function for(?int $tenantId): self
    {
        return new self($tenantId);
    }

    public function get(string $key): mixed
    {
        abort_unless(array_key_exists($key, self::DEFAULTS), 500, "Unknown auth setting $key");
        if (! array_key_exists($key, $this->cache)) {
            $row = DB::table('auth_settings')->where('owner_key', (int) $this->tenantId)->where('key', $key)->first();
            if (! $row) {
                $this->write($key, self::DEFAULTS[$key]);
            }
            $this->cache[$key] = $row ? json_decode($row->value, true) : self::DEFAULTS[$key];
        }

        return $this->cache[$key];
    }

    public function set(string $key, mixed $value): void
    {
        abort_unless(array_key_exists($key, self::DEFAULTS), 500, "Unknown auth setting $key");
        $this->write($key, $value);
        $this->cache[$key] = $value;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return collect(array_keys(self::DEFAULTS))->mapWithKeys(fn ($k) => [$k => $this->get($k)])->all();
    }

    private function write(string $key, mixed $value): void
    {
        DB::table('auth_settings')->upsert(
            [['tenant_id' => $this->tenantId, 'owner_key' => (int) $this->tenantId, 'key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]],
            ['owner_key', 'key'], ['value', 'updated_at'],
        );
    }
}
