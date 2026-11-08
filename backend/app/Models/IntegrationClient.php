<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

/** An external caller (n8n) of the integration API. Token stored hashed, HMAC secret encrypted; never logged. */
class IntegrationClient extends Model
{
    use Audited;

    protected array $auditExcept = ['token_hash', 'hmac_secret', 'token_prefix'];

    protected $fillable = ['name', 'token_prefix', 'token_hash', 'hmac_secret', 'scopes', 'last_used_at', 'revoked_at'];

    protected $hidden = ['token_hash', 'hmac_secret'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'hmac_secret' => 'encrypted', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** True when the client holds at least one of the scopes (`read` | `gift_finder:read` style lists). */
    public function hasScope(string ...$scopes): bool
    {
        return (bool) array_intersect($scopes, $this->scopes ?? []);
    }
}
