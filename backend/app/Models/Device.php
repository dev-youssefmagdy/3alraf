<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A mobile push token (FCM) registered by the app; unique per token. */
class Device extends Model
{

    public const PLATFORMS = ['android', 'ios', 'web'];

    protected $fillable = ['customer_id', 'token', 'token_hash', 'platform', 'locale', 'app_version', 'last_seen_at'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
