<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The store's single WhatsApp account (one row). Credentials live only in the Node service's encrypted storage. */
class WhatsappSession extends Model
{
    protected $fillable = ['session_id', 'is_enabled', 'status', 'phone', 'storage_ref', 'last_connected_at', 'last_event_at', 'last_error'];

    protected $hidden = ['storage_ref'];

    protected function casts(): array
    {
        return ['is_enabled' => 'bool', 'last_connected_at' => 'datetime', 'last_event_at' => 'datetime'];
    }

    /** Fixed gateway session id. Kept as `central` (the id of the already paired device) so the phone never has to re-scan the QR code. */
    public const SESSION_ID = 'central';

    public static function id(): string
    {
        return self::SESSION_ID;
    }

    /** The single session row (created on first use). */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['session_id' => self::SESSION_ID], ['storage_ref' => 'sessions/'.self::SESSION_ID]);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
