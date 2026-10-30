<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One WhatsApp account per owner: tenant_id NULL = the central platform. Credentials live only in the Node service's encrypted storage. */
class WhatsappSession extends Model
{
    protected $fillable = ['tenant_id', 'session_id', 'is_enabled', 'status', 'phone', 'storage_ref', 'last_connected_at', 'last_event_at', 'last_error'];

    protected $hidden = ['storage_ref'];

    protected function casts(): array
    {
        return ['is_enabled' => 'bool', 'last_connected_at' => 'datetime', 'last_event_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $s) => $s->owner_key = (int) $s->tenant_id);
    }

    public static function idFor(?int $tenantId): string
    {
        return $tenantId ? "tenant-$tenantId" : 'central';
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
