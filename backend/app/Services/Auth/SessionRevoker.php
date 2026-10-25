<?php

namespace App\Services\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Kills an account's other logins: rotates its remember_token (all "remember me" cookies die) and deletes the database sessions in which it
 * is logged in on its guard. Sessions are matched by the guard's login key inside the payload (sessions.user_id only tracks the default guard).
 */
class SessionRevoker
{
    /** Model class => session guard. */
    public const GUARDS = [
        \App\Models\Customer::class => 'web',
        \App\Models\Affiliate::class => 'affiliate',
        \App\Models\TenantAdmin::class => 'tenant',
        \App\Models\CentralAdmin::class => 'central',
    ];

    public static function guardFor(Model $user): ?string
    {
        return self::GUARDS[$user::class] ?? null;
    }

    public function revoke(Model&Authenticatable $user, bool $keepCurrentSession = false): void
    {
        $guard = self::guardFor($user);
        if (! $guard) {
            return;
        }

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        $this->deleteSessions($guard, $user->getAuthIdentifier(), $keepCurrentSession && session()->isStarted() ? session()->getId() : null);
    }

    private function deleteSessions(string $guard, int|string $id, ?string $exceptId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }
        $table = DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
        $key = auth()->guard($guard)->getName();
        $doomed = [];
        (clone $table)->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp())
            ->orderBy('id')->select(['id', 'payload'])->chunk(500, function ($rows) use (&$doomed, $key, $id, $exceptId) {
                foreach ($rows as $row) {
                    if ($row->id !== $exceptId && (string) ($this->decode($row->payload)[$key] ?? '') === (string) $id) {
                        $doomed[] = $row->id;
                    }
                }
            });
        foreach (array_chunk($doomed, 500) as $ids) {
            (clone $table)->whereIn('id', $ids)->delete();
        }
    }

    /** @return array<string,mixed> */
    private function decode(string $payload): array
    {
        try {
            $data = base64_decode($payload);
            if (config('session.encrypt')) {
                $data = Crypt::decryptString($data);
            }
            $out = config('session.serialization') === 'json' ? json_decode($data, true) : @unserialize($data, ['allowed_classes' => false]);

            return is_array($out) ? $out : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
